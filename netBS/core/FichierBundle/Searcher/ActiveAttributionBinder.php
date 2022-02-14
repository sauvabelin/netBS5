<?php

namespace NetBS\FichierBundle\Searcher;

use Doctrine\ORM\QueryBuilder;
use NetBS\CoreBundle\Model\BaseBinder;
use NetBS\FichierBundle\Form\Search\SearchActiveAttributionType;
use Symfony\Component\Form\Form;

class ActiveAttributionBinder extends BaseBinder
{
    protected $count = 0;

    public function getType()
    {
        return SearchActiveAttributionType::class;
    }

    public function bind($alias, Form $form, QueryBuilder $builder)
    {
        $active = $form->getData();

        if(!$active)
            return;

        $builder
            ->andWhere($builder->expr()->lt($alias . ".dateDebut", "CURRENT_TIMESTAMP()"))
            ->andWhere($builder->expr()->orX(
                $builder->expr()->isNull($alias . ".dateFin"),
                $builder->expr()->gt($alias .".dateFin", "CURRENT_TIMESTAMP()")
            ));
    }
}
