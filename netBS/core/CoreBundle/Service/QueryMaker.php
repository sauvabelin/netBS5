<?php

namespace NetBS\CoreBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use NetBS\CoreBundle\Model\BinderInterface;
use NetBS\CoreBundle\Model\BindPostFilterHolder;
use Symfony\Component\Form\Form;

class QueryMaker
{
    protected $manager;

    /**
     * @var BinderInterface[]
     */
    protected $binders;

    /**
     * @var int
     */
    protected $aliasIndex   = 0;

    public function __construct(EntityManagerInterface $manager)
    {
        $this->manager  = $manager;
    }

    /**
     * @param BinderInterface $binder
     */
    public function registerBinder(BinderInterface $binder) {

        $this->binders[$binder->getType()]  = $binder;
    }

    /**
     * @return BinderInterface[]
     */
    public function getPostFilters() {
        return array_filter($this->binders, function (BinderInterface $binder) {
            return $binder->getType() === BinderInterface::POST_FILTER;
        });
    }

    public function getResult($itemClass, Form $form) {

        $alias  = '_item';
        $query  = $this->manager->createQueryBuilder()
            ->select($alias)
            ->from($itemClass, $alias);

        $holder = new BindPostFilterHolder();
        $this->concatWith($query, $form, $alias, $holder);
        $result = $query->getQuery()->execute();
        foreach($holder->getBinders() as $data) {
            if ($data['data'] !== null) {
                $subResult = [];

                foreach($result as $item)
                    if ($item !== null && $data['binder']->postFilter($item, $data['data'], $data['options']))
                        $subResult[] = $item;
                $result = $subResult;
            }
        }

        return $result;
    }

    protected function concatWith(QueryBuilder $builder, Form $form, $alias, BindPostFilterHolder $holder) {

        foreach($form as $item) {

            $data   = $item->getNormData();
            $type   = $item->getConfig()->getType()->getInnerType();
            $class  = get_class($type);

            //Binder
            if(isset($this->binders[$class])) {
                if ($this->binders[$class]->bindType() === BinderInterface::BIND)
                    $this->binders[$class]->bind($alias, $item, $builder);
                else $holder->addBinder($this->binders[$class], $item->getData(), $item->getConfig()->getOptions());
            }

            //Children
            elseif($item->count() > 0) {

                $childAlias = $alias . $this->aliasIndex++;
                $wheres     = $this->countWheres($builder);
                $joins      = $builder->getDQLPart('join'); //On sauve les joins d'avant

                $builder->join($alias . '.' . $item->getName(), $childAlias);
                $this->concatWith($builder, $item, $childAlias, $holder);

                if($wheres === $this->countWheres($builder)) {

                    $builder->resetDQLPart('join');

                    foreach($joins as $key => $items) {

                        /** @var Join $join */
                        foreach($items as $join) //On les restaure
                            $builder->join($join->getJoin(), $join->getAlias());
                    }
                }
            }

            elseif($data !== null)
                $this->binders["_netbs.equals"]->bind($alias, $item, $builder);
        }
    }

    protected function countWheres(QueryBuilder $builder) {

        $wheres = $builder->getDQLPart('where');

        if($wheres === null)
            return 0;

        return count($wheres->getParts());
    }
}
