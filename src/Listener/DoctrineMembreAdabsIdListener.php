<?php

namespace App\Listener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use NetBS\CoreBundle\Service\ParameterManager;
use App\Entity\BSMembre;

class DoctrineMembreAdabsIdListener
{
    private $params;



    public function __construct(ParameterManager $params)
    {
        $this->params = $params;
    }

    public function postLoad(LifecycleEventArgs $args) {
        $entity = $args->getEntity();
        if ($entity instanceof BSMembre)
            $entity->_setAdabsId($this->params->getValue('bs', 'groupe.adabs_id'));
    }
}
