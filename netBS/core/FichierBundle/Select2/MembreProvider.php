<?php

namespace NetBS\FichierBundle\Select2;

use NetBS\CoreBundle\Select2\Select2ProviderInterface;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\FichierBundle\Utils\Traits\FichierConfigTrait;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;

class MembreProvider implements Select2ProviderInterface
{
    use EntityManagerTrait, FichierConfigTrait;

    /**
     * Returns the class of the items managed by this provider
     * @return string
     */
    public function getManagedClass()
    {
        return $this->fichierConfig->getMembreClass();
    }

    /**
     * Returns the unique id for the item, used in the select2 field
     * @param BaseMembre $item
     * @return string
     */
    public function toId($item)
    {
        return $item->getId();
    }

    /**
     * Returns string representation of the given managed object
     * @param BaseMembre $item
     * @return string
     */
    public function toString($item)
    {
        return $item->getFullName();
    }

    /**
     * Search for objects related to the given needle
     * @param $term
     * @param int $limit
     * @return array
     */
    public function search($term, $limit = 10)
    {
        if(empty($term))
            return [];

        $query = $this->entityManager->getRepository($this->getManagedClass())
            ->createQueryBuilder('m');

        $results = $query
            ->where("MATCH(m.prenom, m.nom) AGAINST(:term) > 0.8")
            ->setParameter('term', $term . "*")
            ->setMaxResults($limit)
            ->getQuery()
            ->execute();

        $query  = $this->entityManager->getRepository($this->getManagedClass())
            ->createQueryBuilder('m');

        $likeResults = $query
            ->where($query->expr()->orX(
                $query->expr()->like('m.prenom', ':term'),
                $query->expr()->like('m.nom', ':term')
            ))
            ->setParameter('term', '%'.$term.'%')
            ->setMaxResults( $limit - count($results))
            ->getQuery()
            ->execute();

        foreach($likeResults as $likeResult) {

            $in = false;

            foreach($results as $matchAgainstResult)
                if($matchAgainstResult->getId() == $likeResult->getId())
                    $in = true;

            if(!$in)
                $results[] = $likeResult;
        }

        return $results;
    }
}
