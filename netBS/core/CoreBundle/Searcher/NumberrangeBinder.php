<?php

namespace NetBS\CoreBundle\Searcher;

use Doctrine\ORM\QueryBuilder;
use NetBS\CoreBundle\Form\Type\NumberrangeType;
use NetBS\CoreBundle\Model\BaseBinder;
use NetBS\CoreBundle\Model\Numberrange;
use Symfony\Component\Form\Form;

class NumberrangeBinder extends BaseBinder
{
    protected $count = 0;

    public function getType()
    {
        return NumberrangeType::class;
    }

    public function bind($alias, Form $form, QueryBuilder $builder)
    {
        $numberrange    = $form->getData();
        $field          = $alias . "." . $form->getName();

        if(!$numberrange instanceof Numberrange)
            return;

        if($numberrange->getBiggerThan() !== null) {

            $param  = 'numberrange_gt' . $this->count++;
            $builder->andWhere($builder->expr()->gt($field, ':' . $param))
                ->setParameter($param, $numberrange->getBiggerThan());
        }

        if($numberrange->getLowerThan() !== null) {

            $param  = 'numberrange_lt' . $this->count++;
            $builder->andWhere($builder->expr()->lt($field, ':' . $param))
                ->setParameter($param, $numberrange->getLowerThan());
        }
    }
}
