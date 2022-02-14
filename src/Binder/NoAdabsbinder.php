<?php

namespace App\Binder;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use NetBS\CoreBundle\Model\BaseBinder;
use NetBS\CoreBundle\Service\ParameterManager;
use NetBS\FichierBundle\Service\FichierConfig;
use App\Form\Search\SearchNoAdabsType;
use Symfony\Component\Form\Form;

class NoAdabsbinder extends BaseBinder
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
        return SearchNoAdabsType::class;
    }

    public function bind($alias, Form $form, QueryBuilder $builder)
    {
        if(!$form->getData())
            return;

        $adabsId    = $this->params->getValue('bs', "groupe.adabs_id");

        $qb         = $this->manager->createQueryBuilder()->select('m.id')
            ->from($this->config->getMembreClass(), 'm');

        $result = $qb
            ->join('m.attributions', 'a')
            ->where($qb->expr()->lt("a.dateDebut", "CURRENT_TIMESTAMP()"))
            ->andWhere($qb->expr()->orX(
                $qb->expr()->isNull("a.dateFin"),
                $qb->expr()->gt("a.dateFin", "CURRENT_TIMESTAMP()")
            ))
            ->andWhere('a.groupe = :adabsId')
            ->setParameter('adabsId', $adabsId)
            ->getQuery()
            ->getScalarResult();

        $ids    = array_column($result, 'id');

        $builder
            ->andWhere($builder->expr()->notIn("$alias.id", $ids));
    }
}
