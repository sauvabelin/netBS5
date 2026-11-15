<?php

namespace NetBS\FichierBundle\Voter;

use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\FichierBundle\Service\FichierConfig;
use NetBS\SecureBundle\Mapping\BaseUser;

class GroupeVoter extends FichierVoter
{
    public function __construct(FichierConfig $config)
    {
        parent::__construct($config);
    }

    /**
     * Returns the class name of the objects checked in this voter
     * @return string
     */
    protected function supportClass()
    {
        return $this->config->getGroupeClass();
    }

    /**
     * @param string $operation
     * @param BaseGroupe $subject
     * @param BaseUser $user
     * @return bool
     */
    protected function accept($operation, $subject, BaseUser $user)
    {
        $required = 'ROLE_' . strtoupper($operation);

        while($subject !== null) {

            if($user->getMembre())
                foreach ($user->getMembre()->getActivesAttributions() as $attribution)
                    if ($attribution->getGroupe()->getId() === $subject->getId()
                        && $this->grants($attribution->getFonction()->getRoles(), $required))
                        return true;

            foreach ($user->getAutorisations() as $autorisation)
                if($autorisation->getGroupe()->getId() === $subject->getId()
                    && $this->grants($autorisation->getRoles(), $required))
                    return true;

            $subject = $subject->getParent();
        }

        return false;
    }

    /**
     * Exact match on the role or one it implies (ROLE_READ_EVERYWHERE implies ROLE_READ),
     * so a role that merely contains the operation name (ROLE_CREATE_ATTRIBUTION) grants nothing.
     */
    private function grants(iterable $roles, string $required): bool
    {
        foreach ($roles as $role)
            foreach ($role->getChildrenRecursive() as $implied)
                if ($implied->getRole() === $required)
                    return true;

        return false;
    }
}
