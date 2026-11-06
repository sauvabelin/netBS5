<?php

namespace App\Listener;

use Doctrine\ORM\Event\LifecycleEventArgs;
use App\Entity\BSMembre;
use App\Settings\BsReferences;

class DoctrineMembreAdabsIdListener
{
    public function __construct(private readonly BsReferences $refs)
    {
    }

    public function postLoad(LifecycleEventArgs $args) {
        $entity = $args->getEntity();
        if (!$entity instanceof BSMembre)
            return;

        // Runs on every membre load: a cached read, no existence check. An unset or stale id
        // matches no attribution. Keeping the id of a groupe in the trash is what lets
        // BSMembre::setStatut() still close the ADABS attribution of a member being unsubscribed.
        $entity->_setAdabsId($this->refs->id('groupe.adabs_id'));
    }
}
