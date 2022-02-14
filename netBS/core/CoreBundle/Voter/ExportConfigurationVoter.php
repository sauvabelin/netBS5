<?php

namespace NetBS\CoreBundle\Voter;

use NetBS\CoreBundle\Entity\ExportConfiguration;
use NetBS\SecureBundle\Mapping\BaseUser;
use NetBS\SecureBundle\Voter\NetBSVoter;

class ExportConfigurationVoter extends NetBSVoter
{

    /**
     * Returns the class name(s) of the objects checked in this voter
     * @return string|array
     */
    protected function supportClass()
    {
        return ExportConfiguration::class;
    }

    protected function accept($operation, $subject, BaseUser $user)
    {
        return $subject->getUser()->isEqualTo($user);
    }
}