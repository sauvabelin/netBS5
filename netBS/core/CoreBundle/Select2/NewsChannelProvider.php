<?php

namespace NetBS\CoreBundle\Select2;

use NetBS\CoreBundle\Entity\NewsChannel;
use NetBS\CoreBundle\Select2\Select2ProviderInterface;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;

class NewsChannelProvider implements Select2ProviderInterface
{
    use EntityManagerTrait;

    /**
     * Returns the class of the items managed by this provider
     * @return string
     */
    public function getManagedClass()
    {
        return NewsChannel::class;
    }

    /**
     * Returns the unique id for the item, used in the select2 field
     * @param NewsChannel $item
     * @return string
     */
    public function toId($item)
    {
        return $item->getId();
    }

    /**
     * Returns string representation of the given managed object
     * @param NewsChannel $item
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
        $query = $this->entityManager->getRepository(NewsChannel::class)
            ->createQueryBuilder('x');

        return $query
            ->where($query->expr()->like('x.nom', ':n'))
            ->setParameter('n', '%'.$needle.'%')
            ->orderBy('LENGTH(x.nom)', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->execute();
    }
}
