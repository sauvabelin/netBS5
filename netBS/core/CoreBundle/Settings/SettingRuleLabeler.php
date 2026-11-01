<?php

namespace NetBS\CoreBundle\Settings;

use NetBS\CoreBundle\Entity\StoredRule;
use NetBS\CoreBundle\Rule\RuleOwnerLabeler;

/** Rule settings own the rule whose code is their id. */
class SettingRuleLabeler implements RuleOwnerLabeler
{
    public function __construct(private SettingsRegistry $registry)
    {
    }

    public function label(StoredRule $rule): ?string
    {
        foreach ($this->registry->all() as $definition) {
            if ($definition->type === SettingType::Rule && $definition->id() === $rule->getCode()) {
                return sprintf('Paramètre « %s »', $definition->label);
            }
        }

        return null;
    }
}
