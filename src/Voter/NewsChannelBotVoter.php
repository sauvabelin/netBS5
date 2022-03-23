<?php

namespace App\Voter;

use App\Entity\NewsChannelBot;
use NetBS\SecureBundle\Mapping\BaseUser;
use NetBS\SecureBundle\Voter\NetBSVoter;

class NewsChannelBotVoter extends NetBSVoter
{

    /**
     * Returns the class name(s) of the objects checked in this voter
     * @return string|array
     */
    protected function supportClass()
    {
        return [
            NewsChannelBot::class
        ];
    }

    /**
     * Accept or denies the given crud operation on the given subject for the given user
     * @param string $operation a CRUD operation
     * @param $subject
     * @param BaseUser $user
     * @return bool
     */
    protected function accept($operation, $subject, BaseUser $user)
    {
        return $user->hasRole('ROLE_IT');
    }
}