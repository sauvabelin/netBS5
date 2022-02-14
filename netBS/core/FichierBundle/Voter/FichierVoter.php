<?php

namespace NetBS\FichierBundle\Voter;

use NetBS\FichierBundle\Service\FichierConfig;
use NetBS\SecureBundle\Mapping\BaseUser;
use NetBS\SecureBundle\Voter\NetBSVoter;

abstract class FichierVoter extends NetBSVoter
{
    protected $config;

    public function __construct(FichierConfig $config)
    {
        $this->config   = $config;
    }

    protected function specialRule(BaseUser $user)
    {
        return parent::specialRule($user) || $user->hasRole('ROLE_SG');
    }
}