<?php

namespace NetBS\CoreBundle\Listener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use NetBS\CoreBundle\Entity\DynamicList;

class PostLoadDynamicListListener
{
    public function postLoad(LifecycleEventArgs $args) {

        $dynamicList    = $args->getEntity();
        $em             = $args->getEntityManager();

        if(!$dynamicList instanceof DynamicList)
            return;

        $query          = $em->getRepository($dynamicList->getItemsClass())->createQueryBuilder('i');
        $items          = $query->where($query->expr()->in('i.id', ':ids'))
            ->setParameter('ids', $dynamicList->_getItemIds())->getQuery()->getResult();
        $dynamicList->_setItems($items);
    }
}