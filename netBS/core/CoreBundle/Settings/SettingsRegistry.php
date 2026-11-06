<?php

namespace NetBS\CoreBundle\Settings;

use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class SettingsRegistry
{
    /** @var array<string,SettingDefinition>|null keyed by id() */
    private ?array $definitions = null;

    /** @var array<string,string> */
    private array $sections = [];

    /** @param iterable<SettingsProvider> $providers */
    public function __construct(
        #[TaggedIterator('netbs.settings_provider')] private iterable $providers,
    ) {
    }

    public function get(string $ns, string $key): ?SettingDefinition
    {
        return $this->load()[$ns . '.' . $key] ?? null;
    }

    /** @return list<SettingDefinition> */
    public function all(): array
    {
        return array_values($this->load());
    }

    /** @return array<string,string> id => label */
    public function sections(): array
    {
        $this->load();

        return $this->sections;
    }

    /** @return list<SettingDefinition> */
    public function inSection(string $section): array
    {
        return array_values(array_filter($this->load(), fn(SettingDefinition $d) => $d->section === $section));
    }

    /** @return array<string,SettingDefinition> */
    private function load(): array
    {
        if ($this->definitions !== null) {
            return $this->definitions;
        }

        $builder = new SettingsBuilder();
        foreach ($this->providers as $provider) {
            $provider->define($builder);
        }

        $definitions = [];
        foreach ($builder->definitions() as $definition) {
            if (isset($definitions[$definition->id()])) {
                throw new \LogicException(sprintf('Setting "%s" is declared twice.', $definition->id()));
            }
            $definitions[$definition->id()] = $definition;
        }

        $this->sections    = $builder->sections();
        $this->definitions = $definitions;

        return $definitions;
    }
}
