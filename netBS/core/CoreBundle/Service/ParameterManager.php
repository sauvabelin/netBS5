<?php

namespace NetBS\CoreBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Entity\Parameter;
use NetBS\CoreBundle\Entity\StoredRule;
use NetBS\CoreBundle\Repository\StoredRuleRepository;
use NetBS\CoreBundle\Rule\Rule;
use NetBS\CoreBundle\Settings\SettingDefinition;
use NetBS\CoreBundle\Settings\SettingsRegistry;
use NetBS\CoreBundle\Settings\SettingType;
use NetBS\CoreBundle\Settings\SettingValidator;
use Psr\Cache\CacheItemPoolInterface as AdapterInterface;

class ParameterManager
{
    const CACHE_KEY = 'netbs.core.params';

    protected $manager;

    /**
     * @var AdapterInterface
     */
    protected $cache;

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
     * Returns the stored string; a missing declared key falls back to its default (null when it
     * has none). A rule setting returns its rule JSON, or null when unset.
     */
    public function getValue($namespace, $key, $cache = true) {

        $raw        = $cache ? $this->getCachedValue($namespace, $key) : $this->getParameter($namespace, $key)?->getValue();
        $definition = $this->registry->get($namespace, $key);

        if (!$definition)
            return $raw;

        if ($definition->type === SettingType::Rule)
            return $this->rules()->findOneByCode($definition->id())?->getDefinition();

        return $raw ?? $definition->default;
    }

    /**
     * A rule setting lives in the rules table; an empty rule deletes the row, so the
     * feature's legacy logic applies again.
     */
    private function setRule(SettingDefinition $definition, ?string $json): void {

        if (StoredRule::isEmptyJson($json))
            $this->rules()->deleteByCode($definition->id());
        else
            $this->rules()->saveByCode($definition->id(), $definition->context, Rule::fromJson($json)->toJson());

        $this->manager->flush();
    }

    private function rules(): StoredRuleRepository {

        return $this->manager->getRepository(StoredRule::class);
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
