<?php

namespace Ovesco\FacturationBundle\Searcher;

use Doctrine\ORM\QueryBuilder;
use NetBS\CoreBundle\Model\BaseBinder;
use Ovesco\FacturationBundle\Form\Type\CreanceOuverteType;
use Symfony\Component\Form\Form;

class CreanceOuverteBinder extends BaseBinder
{
    public function bindType()
    {
        return self::BIND;
    }

    public function getType()
    {
        return CreanceOuverteType::class;
    }

    public function bind($alias, Form $form, QueryBuilder $builder)
    {
        if ($form->getData() === null) return;

        $query = $form->getData() === 'yes'
            ? $builder->expr()->isNull("$alias.facture")
            : $builder->expr()->isNotNull("$alias.facture");
        $builder->andWhere($query);
    }
}
