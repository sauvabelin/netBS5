<?php

namespace NetBS\FichierBundle\Voter;

use NetBS\FichierBundle\Entity\ObtentionDistinction;
use NetBS\FichierBundle\Utils\FichierHelper;
use NetBS\SecureBundle\Mapping\BaseUser;
use NetBS\SecureBundle\Voter\CRUD;

class ObtentionDistinctionVoter extends FichierVoter
{

    /**
     * Returns the class name of the objects checked in this voter
     * @return string
     */
    protected function supportClass()
    {
        return $this->config->getObtentionDistinctionClass();
    }

    /**
     * @param string $operation
     * @param ObtentionDistinction $subject
     * @param BaseUser $user
     * @return bool
     */
    protected function accept($operation, $subject, BaseUser $user)
    {
        if($operation === CRUD::READ)
            return true;

        return $user->hasRole('ROLE_SG');

        /*
        if($operation === CRUD::READ && $subject->getMembre()->getId() === $user->getMembreId())
            return true;

        foreach ($subject->getMembre()->getActivesAttributions() as $attribution)
            if(parent::accept($operation, $attribution->getGroupe(), $user))
                return true;

        return false;
        */
    }
}