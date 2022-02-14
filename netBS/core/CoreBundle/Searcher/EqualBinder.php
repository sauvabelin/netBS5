<?php

namespace NetBS\CoreBundle\Searcher;

use Doctrine\ORM\QueryBuilder;
use NetBS\CoreBundle\Model\BaseBinder;
use Symfony\Component\Form\Form;

class EqualBinder extends BaseBinder
{
    const KEY        = 'netbs.equals';
    protected $count = 0;

    public function bind($alias, Form $form, QueryBuilder $builder)
    {
        $config = $form->getConfig();
        $data   = $form->getNormData();
        $field  = $alias . "." . $config->getName();
        $param  = '_param' . $this->count++;

        if(is_string($data) && strpos($data, "%") !== false)
            $builder->andWhere($builder->expr()->like($field, ':' . $param));
        elseif($data instanceof \DateTime)
            $builder->andWhere($builder->expr()->eq("DATE($field)", "DATE(:$param)"));
        else
            $builder->andWhere($builder->expr()->eq($field, ':' . $param));

        $builder
            ->setParameter($param, $data);
    }

    public function getType()
    {
        return "_netbs.equals";
    }
}
