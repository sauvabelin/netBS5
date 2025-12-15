<?php

namespace Iacopo\MailingBundle\Voter;

use Iacopo\MailingBundle\Entity\MailingList;
use Iacopo\MailingBundle\Entity\MailingListAlias;
use Iacopo\MailingBundle\Entity\MailingTarget;
use NetBS\SecureBundle\Mapping\BaseUser;
use NetBS\SecureBundle\Voter\NetBSVoter;

class MailingListVoter extends NetBSVoter
{
    protected function supportClass()
    {
        return [
            MailingList::class,
            MailingTarget::class,
            MailingListAlias::class
        ];
    }

    protected function accept($operation, $subject, BaseUser $user)
    {
        return $user->hasRole('ROLE_MAILING');
    }
}
