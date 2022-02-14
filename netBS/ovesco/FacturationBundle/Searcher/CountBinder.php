<?php

namespace Ovesco\FacturationBundle\Searcher;

use Doctrine\ORM\QueryBuilder;
use NetBS\CoreBundle\Model\BaseBinder;
use Ovesco\FacturationBundle\Form\Type\CountSearchType;
use Symfony\Component\Form\Form;

class CountBinder extends BaseBinder
{
    private $index = 0;

    public function bindType()
    {
        return self::BIND;
    }

    public function getType()
    {
        return CountSearchType::class;
    }

    public function bind($alias, Form $form, QueryBuilder $builder)
    {
        $data = $form->getData();
        $property = $form->getConfig()->getOption('property');
        $join = "jcount_items" . $this->index;
        $param = "count_items" . $this->index;

        if(empty($data) && $data !== 0.0) return;

        $builder->leftJoin("$alias.$property", $join)
            ->groupBy("$alias.id")
            ->andHaving("COUNT(DISTINCT $join) = :$param")
            ->setParameter($param, intval($data));

        $this->index++;
    }
}
