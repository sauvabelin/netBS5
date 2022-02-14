<?php

namespace NetBS\CoreBundle\Voter;

use NetBS\CoreBundle\Entity\DynamicList;
use NetBS\SecureBundle\Mapping\BaseUser;
use NetBS\SecureBundle\Voter\NetBSVoter;

class DynamicListVoter extends NetBSVoter
{

    /**
     * Returns the class name(s) of the objects checked in this voter
     * @return string|array
     */
    protected function supportClass()
    {
        return DynamicList::class;
    }

    /**
     * Accept or denies the given crud operation on the given subject for the given user
     * @param string $operation a CRUD operation
     * @param DynamicList $subject
     * @param BaseUser $user
     * @return bool
     */
    protected function accept($operation, $subject, BaseUser $user)
    {
        return $subject->getOwner()->isEqualTo($user);
    }
}