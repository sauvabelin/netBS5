<?php

namespace NetBS\CoreBundle\Voter;

use NetBS\CoreBundle\Entity\News;
use NetBS\CoreBundle\Entity\NewsChannel;
use NetBS\SecureBundle\Mapping\BaseUser;
use NetBS\SecureBundle\Voter\CRUD;
use NetBS\SecureBundle\Voter\NetBSVoter;

class NewsVoter extends NetBSVoter
{

    /**
     * Returns the class name(s) of the objects checked in this voter
     * @return string|array
     */
    protected function supportClass()
    {
        return [
            News::class,
            NewsChannel::class
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
        if($operation === CRUD::READ)
            return true;

        if($subject instanceof NewsChannel)
            return $user->hasRole("ROLE_SG");

        /** @var News $news */
        $news   = $subject;

        if($user->hasRole("ROLE_SG"))
            return true;

        if($news->getUser()->isEqualTo($user))
            return true;

        return false;
    }
}