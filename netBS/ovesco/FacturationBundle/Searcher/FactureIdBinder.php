<?php

namespace Ovesco\FacturationBundle\Searcher;

use Doctrine\ORM\QueryBuilder;
use NetBS\CoreBundle\Model\BaseBinder;
use Ovesco\FacturationBundle\Form\Type\FactureIdType;
use Symfony\Component\Form\Form;

class FactureIdBinder extends BaseBinder
{
    public function bindType()
    {
        return self::BIND;
    }

    public function getType()
    {
        return FactureIdType::class;
    }

    public function bind($alias, Form $form, QueryBuilder $builder)
    {
        $data   = $form->getNormData();
        if (empty($data)) return;

        $builder->andWhere("$alias.oldFichierId = :id OR ($alias.oldFichierId = -1 AND $alias.id = :id)")
            ->setParameter('id', intval($data));
    }
}
