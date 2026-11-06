<?php

namespace NetBS\CoreBundle\Rule;

use NetBS\CoreBundle\Entity\StoredRule;

/** Names the owner of a stored rule, for netbs:rules:check (tag netbs.rule_owner_labeler). */
interface RuleOwnerLabeler
{
    /** e.g. « Modèle de facture « 1 rappel » » when this labeler's objects own the rule, else null */
    public function label(StoredRule $rule): ?string;
}
