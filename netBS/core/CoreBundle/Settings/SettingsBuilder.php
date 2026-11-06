<?php

namespace NetBS\CoreBundle\Settings;

class SettingsBuilder
{
    private ?string $section = null;

    /** @var array<string,string> */
    private array $sections = [];

    /** @var list<SettingDefinition> */
    private array $definitions = [];

    public function section(string $id, string $label): self
    {
        $this->section        = $id;
        $this->sections[$id]  = $label;

        return $this;
    }

    public function text(string $ns, string $key, string $label, ?string $default = null, ?string $help = null): self
    {
        return $this->add($ns, $key, SettingType::Text, $label, help: $help, default: $default);
    }

    public function int(string $ns, string $key, string $label, ?int $default = null, ?int $min = null, ?int $max = null, ?string $help = null): self
    {
        return $this->add($ns, $key, SettingType::Int, $label, help: $help,
            default: $default === null ? null : (string) $default, min: $min, max: $max);
    }

    /** No default: unset means the feature is closed. */
    public function secret(string $ns, string $key, string $label, ?int $minLength = null, ?string $help = null): self
    {
        return $this->add($ns, $key, SettingType::Secret, $label, help: $help, minLength: $minLength);
    }

    /** @param array<string,string> $choices value => label */
    public function choice(string $ns, string $key, string $label, array $choices, ?string $default = null, ?string $help = null): self
    {
        return $this->add($ns, $key, SettingType::Choice, $label, help: $help, default: $default, choices: $choices);
    }

    /** Stored in the rules table by code = "<ns>.<key>"; unset means the legacy logic applies. */
    public function rule(string $ns, string $key, string $label, string $context, ?string $help = null): self
    {
        return $this->add($ns, $key, SettingType::Rule, $label, help: $help, context: $context);
    }

    /** @return list<SettingDefinition> */
    public function definitions(): array
    {
        return $this->definitions;
    }

    /** @return array<string,string> id => label, in declaration order */
    public function sections(): array
    {
        return $this->sections;
    }

    private function add(string $ns, string $key, SettingType $type, string $label, mixed ...$options): self
    {
        if ($this->section === null) {
            throw new \LogicException(sprintf('Setting "%s.%s" is declared outside of a section.', $ns, $key));
        }

        $this->definitions[] = new SettingDefinition($ns, $key, $type, $label, $this->section, ...$options);

        return $this;
    }
}
