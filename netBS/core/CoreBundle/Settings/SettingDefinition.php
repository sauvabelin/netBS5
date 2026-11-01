<?php

namespace NetBS\CoreBundle\Settings;

/**
 * One declared setting. Its storage identity is (namespace, key) in netbs_core_parameters, except
 * rule settings, stored in netbs_core_rules with code = id();
 * the section only decides on which card of the Paramètres page it is shown.
 */
final class SettingDefinition
{
    /**
     * @param array<string,string> $choices value => label, for SettingType::Choice
     * @param string|null $target 'groupe'|'fonction'|'groupe_type'|'groupe_categorie', for SettingType::Entity
     * @param string|null $context rule context id, for SettingType::Rule
     */
    public function __construct(
        public readonly string $namespace,
        public readonly string $key,
        public readonly SettingType $type,
        public readonly string $label,
        public readonly string $section,
        public readonly ?string $help = null,
        public readonly bool $important = false,
        public readonly ?string $default = null,
        public readonly ?int $min = null,
        public readonly ?int $max = null,
        public readonly ?int $minLength = null,
        public readonly array $choices = [],
        public readonly ?string $target = null,
        public readonly ?string $context = null,
    ) {
        // A rule has neither: no rule set means the feature's legacy logic applies.
        if ($type === SettingType::Rule) {
            if ($important || $default !== null || $context === null) {
                throw new \LogicException(sprintf('Rule setting "%s" needs a context and no default or important.', $this->id()));
            }
        } elseif ($important === ($default !== null)) {
            throw new \LogicException(sprintf('Setting "%s" must have exactly one of a default or important: true.', $this->id()));
        }
    }

    public function id(): string
    {
        return $this->namespace . '.' . $this->key;
    }
}
