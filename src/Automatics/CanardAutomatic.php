<?php

namespace App\Automatics;

use NetBS\CoreBundle\Model\Automatic\BirthdayData;
use NetBS\CoreBundle\Model\BaseAutomatic;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\CoreBundle\Utils\Traits\ParamTrait;
use NetBS\FichierBundle\Utils\ListModel\MembreListHelper;
use NetBS\FichierBundle\Utils\Traits\FichierConfigTrait;

class CanardAutomatic extends BaseAutomatic
{
    use MembreListHelper, FichierConfigTrait, EntityManagerTrait, ParamTrait;

    /**
     * @return string
     */
    public function getName()
    {
        return "Canard Encordé";
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return "Tous les actifs (soutiens et routiers inclus) sans les responsables";
    }

    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return 'tdgl.automatic.canard';
    }

    /**
     * @param BirthdayData $data
     * @return array|mixed
     */
    protected function getItems($data = null)
    {
        $actifsFn = explode(',', $this->parameterManager->getValue('tdgl', 'fonction.actifs_ids'));
        $qb = $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from($this->getFichierConfig()->getMembreClass(), 'm');

        $qb->join('m.attributions', 'a')
            ->where($qb->expr()->lt("a.dateDebut", "CURRENT_TIMESTAMP()"))
            ->andWhere($qb->expr()->orX(
                $qb->expr()->isNull("a.dateFin"),
                $qb->expr()->gt("a.dateFin", "CURRENT_TIMESTAMP()")
            ))
            ->andWhere($qb->expr()->in('a.fonction', $actifsFn))
            ->getQuery();

        return $qb->getQuery()->execute();
    }
}
