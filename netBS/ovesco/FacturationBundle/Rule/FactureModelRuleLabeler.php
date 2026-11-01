<?php

namespace Ovesco\FacturationBundle\Rule;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Entity\StoredRule;
use NetBS\CoreBundle\Rule\RuleOwnerLabeler;
use Ovesco\FacturationBundle\Entity\FactureModel;

class FactureModelRuleLabeler implements RuleOwnerLabeler
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function label(StoredRule $rule): ?string
    {
        $model = $this->em->getRepository(FactureModel::class)->findOneBy(['rule' => $rule]);

        return $model ? sprintf('Modèle de facture « %s »', $model->getName()) : null;
    }
}
