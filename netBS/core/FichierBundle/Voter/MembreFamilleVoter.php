<?php

namespace NetBS\FichierBundle\Voter;

use Doctrine\Common\Util\ClassUtils;
use NetBS\FichierBundle\Mapping\BaseFamille;
use NetBS\FichierBundle\Mapping\BaseGeniteur;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\SecureBundle\Mapping\BaseUser;
use NetBS\SecureBundle\Voter\CRUD;

class MembreFamilleVoter extends GroupeVoter
{

    /**
     * Returns the class name of the objects checked in this voter
     * @return string|array
     */
    protected function supportClass()
    {
        return [
            $this->config->getMembreClass(),
            $this->config->getFamilleClass(),
            $this->config->getGeniteurClass(),
        ];
    }

    protected function accept($operation, $subject, BaseUser $user)
    {
        if(ClassUtils::getClass($subject) === $this->config->getMembreClass())
            return $this->acceptMembre($operation, $subject, $user);
        elseif(ClassUtils::getClass($subject) === $this->config->getFamilleClass())
            return $this->acceptFamille($operation, $subject, $user);
        elseif(ClassUtils::getClass($subject) === $this->config->getGeniteurClass())
            return $this->acceptGeniteur($operation, $subject, $user);
        return false;
    }

    /**
     * Accept or denies the given crud operation on the given subject for the given user
     * @param string $operation a CRUD operation
     * @param BaseMembre $subject
     * @param BaseUser $user
     * @return bool
     */
    protected function acceptMembre($operation, $subject, BaseUser $user)
    {
        if($this->isSelfOperation($operation) && $user->getMembre() && $subject->getId() === $user->getMembreId())
            return true;

        foreach($subject->getActivesAttributions() as $attribution)
            if(parent::accept($operation, $attribution->getGroupe(), $user))
                return true;

        return false;
    }

    /**
     * Accept or denies the given crud operation on the given subject for the given user
     * @param string $operation a CRUD operation
     * @param BaseFamille $subject
     * @param BaseUser $user
     * @return bool
     */
    protected function acceptFamille($operation, $subject, BaseUser $user)
    {
        if($this->isSelfOperation($operation) && $user->getMembre() && $subject->getId() === $user->getMembre()->getFamille()?->getId())
            return true;

        foreach($subject->getMembres() as $membre)
            if($this->acceptMembre($operation, $membre, $user))
                return true;

        return false;
    }

    /**
     * Accept or denies the given crud operation on the given subject for the given user
     * @param string $operation a CRUD operation
     * @param BaseGeniteur $subject
     * @param BaseUser $user
     * @return bool
     */
    protected function acceptGeniteur($operation, $subject, BaseUser $user)
    {
        return $this->acceptFamille($operation, $subject->getFamille(), $user);
    }

    /**
     * Your own membre/famille/géniteurs: you may see and edit them (edits are
     * further limited by the entity's #[User] constraint), not create or delete.
     */
    private function isSelfOperation(string $operation): bool
    {
        return in_array($operation, [CRUD::READ, CRUD::UPDATE], true);
    }
}
