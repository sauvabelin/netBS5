<?php

namespace App\Select2;

use App\Entity\CabaneTimePeriod;
use NetBS\CoreBundle\Select2\Select2ProviderInterface;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;

class CabaneTimePeriodProvider implements Select2ProviderInterface
{
    use EntityManagerTrait;

    public function getManagedClass()
    {
        return CabaneTimePeriod::class;
    }

    /**
     * Returns the unique id for the item, used in the select2 field
     * @return string
     */
    public function toId($item)
    {
        return $item->getId();
    }

    /**
     * Returns string representation of the given managed object
     * @param CabaneTimePeriod $item
     * @return string
     */
    public function toString($item)
    {
        return $item->getNom();
    }

    /**
     * Search for objects related to the given needle
     * @param $needle
     * @param int $limit
     * @return array
     */
    public function search($needle, $limit = 5)
    {
        $query = $this->entityManager->getRepository($this->getManagedClass())
            ->createQueryBuilder('x');

        return $query
            ->where($query->expr()->like('x.nom', ':n'))
            ->setParameter('n', '%'.$needle.'%')
            ->setMaxResults($limit)
            ->getQuery()
            ->execute();
    }
}