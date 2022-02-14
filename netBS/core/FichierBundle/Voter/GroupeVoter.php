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
        while($subject !== null) {

            // Check attributions
            if($user->getMembre())
                foreach ($user->getMembre()->getActivesAttributions() as $attribution)
                    if ($attribution->getGroupe()->getId() === $subject->getId())
                        foreach ($attribution->getFonction()->getRoles() as $role)
                            if(strpos(strtoupper($role->getRole()), strtoupper($operation)) !== false)
                                return true;

            // Check autorisations
            foreach ($user->getAutorisations() as $autorisation)
                if($autorisation->getGroupe()->getId() === $subject->getId())
                    foreach($autorisation->getRoles() as $role)
                        if(strpos(strtoupper($role->getRole()), strtoupper($operation)) !== false)
                            return true;

            $subject = $subject->getParent();
        }

        return false;
    }
}