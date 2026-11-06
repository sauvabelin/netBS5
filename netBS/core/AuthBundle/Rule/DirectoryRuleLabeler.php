<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Rule;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\AuthBundle\Entity\DirectoryComputedGroup;
use NetBS\AuthBundle\Entity\DirectoryPublicationRule;
use NetBS\CoreBundle\Entity\StoredRule;
use NetBS\CoreBundle\Rule\RuleOwnerLabeler;

/** Names the LDAP publication rule or computed group owning a stored rule. */
class DirectoryRuleLabeler implements RuleOwnerLabeler
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function label(StoredRule $rule): ?string
    {
        if ($rule->getId() === null) {
            return null;
        }

        $publication = $this->em->getRepository(DirectoryPublicationRule::class)->createQueryBuilder('r')
            ->where('r.selection = :rule OR r.sousGroupesRule = :rule')
            ->setParameter('rule', $rule)
            ->getQuery()
            ->getOneOrNullResult();
        if ($publication !== null) {
            return sprintf('Règle de partage « %s »', $publication->getLabel());
        }

        $computed = $this->em->getRepository(DirectoryComputedGroup::class)->findOneBy(['membres' => $rule]);

        return $computed ? sprintf('Groupe LDAP calculé « %s »', $computed->getName()) : null;
    }
}
