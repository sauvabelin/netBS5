<?php

namespace NetBS\FichierBundle\Select2;

use NetBS\CoreBundle\Select2\Select2ProviderInterface;
use NetBS\FichierBundle\Mapping\BaseGroupeType;
use NetBS\FichierBundle\Utils\Traits\FichierConfigTrait;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;

class GroupeTypeProvider implements Select2ProviderInterface
{
    use FichierConfigTrait, EntityManagerTrait;

    /**
     * Returns the class of the items managed by this provider
     * @return string
     */
    public function getManagedClass()
    {
        return $this->fichierConfig->getGroupeTypeClass();
    }

    /**
     * Returns string representation of the given managed object
     * @param BaseGroupeType $item
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

    /**
     * Returns the unique id for the item, used in the select2 field
     * @param BaseGroupeType $item
     * @return string
     */
    public function toId($item)
    {
        return $item->getId();
    }
}