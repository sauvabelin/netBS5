<?php

namespace Ovesco\FacturationBundle\Searcher;

use Doctrine\ORM\QueryBuilder;
use NetBS\CoreBundle\Model\BaseBinder;
use Ovesco\FacturationBundle\Form\Type\CreanceInFactureType;
use Symfony\Component\Form\Form;

class CreanceInFactureBinder extends BaseBinder
{
    protected $count = 0;

    public function bind($alias, Form $form, QueryBuilder $builder)
    {
        $data   = $form->getNormData();
        $param  = '_creance_name' . $this->count++;
        if (empty($data)) return;

        $builder->join("$alias.creances", 'creances');

        if(is_string($data) && strpos($data, "%") !== false)
            $builder->andWhere($builder->expr()->like('creances.titre', ':' . $param));
        else
            $builder->andWhere($builder->expr()->eq('creances.titre', ':' . $param));

        $builder
            ->setParameter($param, $data);
    }

    public function getType()
    {
        return CreanceInFactureType::class;
    }
}
