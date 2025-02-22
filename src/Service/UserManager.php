<?php

namespace App\Service;

use App\Entity\LatestCreatedAccount;
use NetBS\SecureBundle\Mapping\BaseUser;
use NetBS\SecureBundle\Service\UserManager as UM;

class UserManager extends UM
{
    public function deleteUser(BaseUser $user)
    {
        $latestAccounts = $this->em->getRepository(LatestCreatedAccount::class)->findBy([
            'user' => $user
        ]);

        foreach($latestAccounts as $la)
            $this->em->remove($la);

        $this->em->flush();

        parent::deleteUser($user);
    }
}
