<?php

namespace App\Automatics;

use NetBS\CoreBundle\Model\BaseAutomatic;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\CoreBundle\Utils\Traits\ParamTrait;
use NetBS\FichierBundle\Mapping\BaseAttribution;
use NetBS\FichierBundle\Utils\ListModel\MembreListHelper;
use NetBS\FichierBundle\Utils\Traits\FichierConfigTrait;
use App\Model\GarsQuiBougentData;
use NetBS\FichierBundle\Entity\Attribution;

class TestDentreeAutomatic extends BaseAutomatic
{
    use FichierConfigTrait, ParamTrait, EntityManagerTrait, MembreListHelper;

    /**
     * @return string
     * Returns this list's name, displayed
     */
    public function getName()
    {
        return "Inscriptions au test d'entrée";
    }

    /**
     * @return string
     * Returns this list's description, displayed
     */
    public function getDescription()
    {
        return "Récupère la liste des chefs à inscrire au test d'entrée, c'est-à-dire les chefs de deuxième année";
    }

    /**
     * @param GarsQuiBougentData $data
     * @return array
     */
    protected function getItems($data = null)
    {
        $now        = new \DateTime();
        $deuxieme   = intval($now->format('Y')) - 1;
        $fnIds      = [
            $this->parameterManager->getValue('bs', 'fonction.cp_id'),
            $this->parameterManager->getValue('bs', 'fonction.cl_id'),
            $this->parameterManager->getValue('bs', 'fonction.rouge_id')
        ];

        $fnIds  = array_map(function($i) {return intval($i);}, $fnIds);
        $query  = $this->entityManager->getRepository(Attribution::class)
            ->createQueryBuilder('attr');

        $result = $query
            ->join('attr.fonction', 'fn')
            ->join('attr.membre', 'mbr')
            ->where($query->expr()->in('fn.id', $fnIds))
            ->andWhere('attr.dateDebut < :now')
            ->andWhere($query->expr()->orX(
                $query->expr()->isNull('attr.dateFin'),
                $query->expr()->gt('attr.dateFin', ':now')
            ))
            ->setParameter('now', $now)
            ->andWhere('YEAR(attr.dateDebut) = :deuxieme')
            ->setParameter('deuxieme', $deuxieme)
            ->getQuery()
            ->getResult();

        return array_map(function (BaseAttribution $attribution) {
            return $attribution->getMembre();
        }, $result);
    }


    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return "sauvabelin.test_entree";
    }
}
