<?php

namespace NetBS\CoreBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Entity\Parameter;
use NetBS\CoreBundle\Entity\StoredRule;
use NetBS\CoreBundle\Repository\StoredRuleRepository;
use NetBS\CoreBundle\Rule\Rule;
use NetBS\CoreBundle\Settings\SettingDefinition;
use NetBS\CoreBundle\Settings\SettingNotConfiguredException;
use NetBS\CoreBundle\Settings\SettingProblem;
use NetBS\CoreBundle\Settings\SettingsRegistry;
use NetBS\CoreBundle\Settings\SettingType;
use NetBS\CoreBundle\Settings\SettingValidator;
use Psr\Cache\CacheItemPoolInterface as AdapterInterface;
use Symfony\Contracts\Service\ResetInterface;

class ParameterManager implements ResetInterface
{
    const CACHE_KEY = 'netbs.core.params';

    protected $manager;

    /**
     * @var AdapterInterface
     */
    protected $cache;

    /** @var array<string,bool> setting id + raw value => does the reference resolve */
    private array $resolvedReferences = [];

    public function __construct(
        EntityManagerInterface $manager,
        AdapterInterface $cache,
        private SettingsRegistry $registry,
        private SettingValidator $validator,
    ) {
        $this->manager  = $manager;
        $this->cache    = $cache;
    }

    public function getParameter($namespace, $key) {

        return $this->manager->getRepository(Parameter::class)->findOneBy(array(
            'namespace' => $namespace,
            'paramKey'  => $key
        ));
    }

    public function getParameters($namespace) {

        return $this->manager->getRepository(Parameter::class)
            ->findBy(array('namespace' => $namespace));
    }

    /**
     * Declared keys are validated and throw \InvalidArgumentException with the validator
     * message; undeclared (legacy) keys are stored as-is. Missing rows are created.
     */
    public function setValue($namespace, $key, $value) {

        $definition = $this->registry->get($namespace, $key);
        $value      = $value === null ? null : (string) $value;

        if ($definition && ($error = $this->validator->validate($definition, $value)) !== null)
            throw new \InvalidArgumentException($error);

        if ($definition?->type === SettingType::Rule) {
            $this->setRule($definition, $value);
            return;
        }

        $param      = $this->getParameter($namespace, $key) ?? new Parameter($namespace, $key, $value);
        $param->setValue($value);
        $this->manager->persist($param);
        $this->manager->flush();

        $this->refreshCache($param);
    }

    /**
     * Returns the stored string. A missing declared key falls back to its default, or throws
     * SettingNotConfiguredException when it is important; so does an important reference to a
     * deleted row. That reference check is memoized only until the next Doctrine flush (see
     * forgetResolvedReferences()), so a delete/restore applies at once.
     */
    public function getValue($namespace, $key, $cache = true) {

        $raw        = $cache ? $this->getCachedValue($namespace, $key) : $this->getParameter($namespace, $key)?->getValue();
        $definition = $this->registry->get($namespace, $key);

        if (!$definition)
            return $raw;

        if ($definition->type === SettingType::Rule)
            return $this->rules()->findOneByCode($definition->id())?->getDefinition();

        if ($raw === null || ($definition->important && trim($raw) === '')) {
            if ($definition->important)
                throw new SettingNotConfiguredException($definition);

            return $definition->default;
        }

        if ($definition->type === SettingType::Entity && $definition->important
            && !$this->referenceResolves($definition, $raw))
            throw new SettingNotConfiguredException($definition);

        return $raw;
    }

    /**
     * Every important setting that is missing or invalid, and every declared setting whose
     * stored value is invalid. Not cached.
     *
     * @return SettingProblem[]
     */
    public function problems(): array {

        $stored     = [];
        foreach ($this->manager->getRepository(Parameter::class)->findAll() as $parameter)
            $stored[$parameter->getNamespace() . '.' . $parameter->getKey()] = $parameter->getValue();

        $problems   = [];
        $toValidate = [];
        foreach ($this->registry->all() as $definition) {

            if ($definition->type === SettingType::Rule) {
                $rule = $this->rules()->findOneByCode($definition->id());
                if ($rule && ($error = $this->validator->validate($definition, $rule->getDefinition())) !== null)
                    $problems[] = new SettingProblem($definition, $error);

                continue;
            }

            $raw    = $stored[$definition->id()] ?? null;

            if ($raw === null) {
                if ($definition->important)
                    $problems[] = new SettingProblem($definition, 'Non configuré');

                continue;
            }

            $toValidate[] = [$definition, $raw];
        }

        $errors     = $this->validator->validateAll($toValidate);
        foreach ($toValidate as [$definition, $raw])
            if (isset($errors[$definition->id()]))
                $problems[] = new SettingProblem($definition, $errors[$definition->id()]);

        return $problems;
    }

    /**
     * A rule setting lives in the rules table; an empty rule deletes the row, so the
     * feature's legacy logic applies again.
     */
    private function setRule(SettingDefinition $definition, ?string $json): void {

        if ($json === null || Rule::fromJson($json)->isEmpty())
            $this->rules()->deleteByCode($definition->id());
        else
            $this->rules()->saveByCode($definition->id(), $definition->context, Rule::fromJson($json)->toJson());

        $this->manager->flush();
    }

    private function rules(): StoredRuleRepository {

        return $this->manager->getRepository(StoredRule::class);
    }

    /**
     * The stored string as-is: no default, no reference check, never throws. Only for callers
     * that must not lose a stored id while its row is in the trash (see
     * DoctrineMembreAdabsIdListener); everything else uses getValue().
     */
    public function getStoredValue($namespace, $key): ?string {

        return $this->getCachedValue($namespace, $key);
    }

    /**
     * Called on every Doctrine flush: a flush may have deleted or restored a referenced row.
     */
    public function forgetResolvedReferences(): void {

        $this->resolvedReferences = [];
    }

    /**
     * Between messages of the long-running messenger worker, which does not always flush.
     */
    public function reset(): void {

        $this->forgetResolvedReferences();
    }

    private function referenceResolves(SettingDefinition $definition, string $raw): bool {

        return $this->resolvedReferences[$definition->id() . '#' . $raw]
            ??= $this->validator->resolveEntity($definition, $raw) !== null;
    }

    public function refresh() {

        $parameters = $this->manager->getRepository(Parameter::class)->findAll();

        foreach ($parameters as $parameter)
            $this->refreshCache($parameter);
    }

    public function refreshCache(Parameter $parameter): void {

        $this->storeInCache($parameter->getNamespace(), $parameter->getKey(), $parameter->getValue());
    }

    public function forgetCache(Parameter $parameter): void {

        $this->cache->deleteItem($this->getCachePath($parameter->getNamespace(), $parameter->getKey()));
    }

    protected function getCachedValue($namespace, $key): ?string {

        $item       = $this->cache->getItem($this->getCachePath($namespace, $key));

        if($item->isHit())
            return $item->get();

        $parameter  = $this->getParameter($namespace, $key);

        // A missing row is not cached: a row added later (by SQL, by another request) must be seen.
        return $parameter ? $this->storeInCache($namespace, $key, $parameter->getValue()) : null;
    }

    protected function storeInCache($namespace, $key, ?string $value): ?string {

        $item   = $this->cache->getItem($this->getCachePath($namespace, $key));

        $item->set($value);
        $this->cache->save($item);

        return $value;
    }

    protected function getCachePath($namespace, $key) {

        return self::CACHE_KEY . '.' . $namespace . '.' . $key;
    }
}
