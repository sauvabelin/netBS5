<?php

namespace NetBS\SecureBundle\Select2;

use Doctrine\Common\Collections\Collection;
use NetBS\CoreBundle\Select2\Select2ProviderInterface;
use NetBS\FichierBundle\Utils\Traits\SecureConfigTrait;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\SecureBundle\Mapping\BaseRole;

class RolesProvider implements Select2ProviderInterface
{
    use EntityManagerTrait, SecureConfigTrait;

    /**
     * Returns the class of the items managed by this provider
     * @return string
     */
    public function getManagedClass()
    {
        return $this->getSecureConfig()->getRoleClass();
    }

    /**
     * Returns the unique id for the item, used in the select2 field
     * @param BaseRole $item
     * @return string
     */
    public function toId($item)
    {
        return $item->getId();
    }

    /**
     * Returns string representation of the given managed object
     * @param BaseRole $item
     * @return string
     */
    public function toString($item)
    {
        return $item->getRole() . " - " . $item->getDescription();
    }

    /**
     * Search for objects related to the given needle
     * @param $needle
     * @param int $limit
     * @return Collection
     */
    public function search($needle, $limit = 5)
    {
        return $this->entityManager->getRepository($this->getManagedClass())->createQueryBuilder('r')
            ->where('r.role LIKE :role')
            ->setParameter('role', '%' . $needle . '%')
            ->getQuery()
            ->execute();
    }
}