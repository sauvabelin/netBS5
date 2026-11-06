<?php

namespace NetBS\CoreBundle\Repository;

use Doctrine\ORM\EntityRepository;
use NetBS\CoreBundle\Entity\StoredRule;

class StoredRuleRepository extends EntityRepository
{
    public function findOneByCode(string $code): ?StoredRule
    {
        return $this->findOneBy(['code' => $code]);
    }

    /** Creates the row if missing, else updates its definition; does not flush. */
    public function saveByCode(string $code, string $context, string $json): StoredRule
    {
        $row = $this->findOneByCode($code);
        if ($row === null) {
            $row = new StoredRule($context, $json, $code);
            $this->getEntityManager()->persist($row);
        } else {
            $row->setDefinition($json);
        }

        return $row;
    }

    /** Does not flush. */
    public function deleteByCode(string $code): void
    {
        $row = $this->findOneByCode($code);
        if ($row !== null) {
            $this->getEntityManager()->remove($row);
        }
    }
}
