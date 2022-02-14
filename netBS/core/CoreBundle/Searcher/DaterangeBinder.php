<?php

namespace NetBS\CoreBundle\Searcher;

use Doctrine\ORM\QueryBuilder;
use NetBS\CoreBundle\Form\Type\DaterangeType;
use NetBS\CoreBundle\Model\BaseBinder;
use NetBS\CoreBundle\Model\Daterange;
use Symfony\Component\Form\Form;

class DaterangeBinder extends BaseBinder
{
    protected $count = 0;

    public function getType()
    {
        return DaterangeType::class;
    }

    public function bind($alias, Form $form, QueryBuilder $builder)
    {
        $daterange  = $form->getData();
        $field      = $alias . "." . $form->getName();

        if(!$daterange instanceof Daterange)
            return;

        if($daterange->getBiggerThan() instanceof \DateTime) {

            $param  = 'daterange_gt' . $this->count++;
            $builder->andWhere($builder->expr()->gt($field, ':' . $param))
                ->setParameter($param, $daterange->getBiggerThan());
        }


        if($daterange->getLowerThan() instanceof \DateTime) {

            $param  = 'daterange_lt' . $this->count++;
            $builder->andWhere($builder->expr()->lt($field, ':' . $param))
                ->setParameter($param, $daterange->getLowerThan());
        }
    }
}
