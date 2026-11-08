<?php

namespace App\Service;

use App\Entity\LatestCreatedAccount;
use NetBS\SecureBundle\Mapping\BaseUser;
use NetBS\SecureBundle\Service\UserManager as UM;

class UserManager extends UM
{
    public function deleteUser(BaseUser $user)
    {
        $this->forgetInitialPassword($user);
        $this->em->flush();

        parent::deleteUser($user);
    }

    /**
     * Drops the plaintext initial password stored for the user (LatestCreatedAccount), once it
     * is no longer the user's password. Not flushed.
     */
    public function forgetInitialPassword(BaseUser $user): void
    {
        foreach ($this->em->getRepository(LatestCreatedAccount::class)->findBy(['user' => $user]) as $latest)
            $this->em->remove($latest);
    }
}
