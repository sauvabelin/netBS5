<?php

namespace NetBS\CoreBundle\Settings;

/**
 * Thrown when an important setting is read while missing, or while it references a row that
 * no longer exists. The feature reading it must not run on a guessed value.
 */
class SettingNotConfiguredException extends \RuntimeException
{
    public function __construct(private SettingDefinition $definition)
    {
        parent::__construct(sprintf('Le paramètre « %s » n\'est pas configuré (Paramètres système).', $definition->label));
    }

    public function getDefinition(): SettingDefinition
    {
        return $this->definition;
    }
}
