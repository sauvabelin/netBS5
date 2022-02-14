<?php

namespace NetBS\SecureBundle\Voter;

use NetBS\SecureBundle\Mapping\BaseUser;
use NetBS\SecureBundle\Service\SecureConfig;

class UserVoter extends NetBSVoter
{
    private $config;

    public function __construct(SecureConfig $config)
    {
        $this->config   = $config;
    }

    /**
     * Returns the class name(s) of the objects checked in this voter
     * @return string|array
     */
    protected function supportClass()
    {
        return $this->config->getUserClass();
    }

    /**
     * Accept or denies the given crud operation on the given subject for the given user
     * @param string $operation a CRUD operation
     * @param BaseUser $subject
     * @param BaseUser $user
     * @return bool
     */
    protected function accept($operation, $subject, BaseUser $user)
    {
        return $subject->isEqualTo($user);
    }
}