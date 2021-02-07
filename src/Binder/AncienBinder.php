<?php

namespace App\Binder;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use NetBS\CoreBundle\Model\BaseBinder;
use NetBS\CoreBundle\Service\ParameterManager;
use NetBS\FichierBundle\Service\FichierConfig;
use Symfony\Component\Form\Form;
use App\Form\AncienType;

class AncienBinder extends BaseBinder
{
    private $config;

    private $params;

    private $manager;

    public function __construct(FichierConfig $config, EntityManagerInterface $manager, ParameterManager $params)
    {
        $this->config   = $config;
        $this->params   = $params;
        $this->manager  = $manager;
    }

    public function getType()
    {
        return AncienType::class;
    }

    public function bind($alias, Form $form, QueryBuilder $builder)
    {
        $inclure    = $form->getData() === true;
        if ($inclure) return;

        $ancienId   = $this->params->getValue('tdgl', "groupe.anciens_id");
        $qb         = $this->manager->createQueryBuilder()->select('m.id')
            ->from($this->config->getMembreClass(), 'm');

        $result = $qb
            ->join('m.attributions', 'a')
            ->where($qb->expr()->lt("a.dateDebut", "CURRENT_TIMESTAMP()"))
            ->andWhere($qb->expr()->orX(
                $qb->expr()->isNull("a.dateFin"),
                $qb->expr()->gt("a.dateFin", "CURRENT_TIMESTAMP()")
            ))
            ->andWhere('a.groupe = :ancienId')
            ->setParameter('ancienId', $ancienId)
            ->getQuery()
            ->getScalarResult();

        $ids    = array_column($result, 'id');

        if (count($ids) > 0) {
            $builder
                ->andWhere($builder->expr()->notIn("$alias.id", $ids));
        }
    }
}
