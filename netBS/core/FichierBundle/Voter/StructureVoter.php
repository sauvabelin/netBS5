<?php

namespace NetBS\FichierBundle\Voter;

use NetBS\SecureBundle\Mapping\BaseUser;

class StructureVoter extends FichierVoter
{
    /**
     * Returns the class name of the objects checked in this voter
     */
    protected function supportClass()
    {
        return [
            $this->config->getDistinctionClass(),
            $this->config->getGroupeTypeClass(),
            $this->config->getGroupeCategorieClass(),
            $this->config->getFonctionClass(),
        ];
    }

    protected function accept($operation, $subject, BaseUser $user)
    {
    }
}