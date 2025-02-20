<?php

namespace App\Voter;

use App\Entity\APMBSReservation;
use App\Entity\Cabane;
use App\Entity\CabaneTimePeriod;
use App\Entity\Intendant;
use App\Entity\ReservationLog;
use NetBS\SecureBundle\Mapping\BaseUser;
use NetBS\SecureBundle\Voter\NetBSVoter;

class APMBSVoter extends NetBSVoter
{
    protected function supportClass()
    {
        return [
            Intendant::class,
            Cabane::class,
            APMBSReservation::class,
            CabaneTimePeriod::class,
            ReservationLog::class
        ];
    }

    protected function accept($operation, $subject, BaseUser $user)
    {
        return $user->hasRole('ROLE_APMBS');
    }
}