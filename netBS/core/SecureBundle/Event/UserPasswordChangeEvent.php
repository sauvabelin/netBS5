<?php

namespace NetBS\SecureBundle\Event;

use NetBS\SecureBundle\Mapping\BaseUser;
use Symfony\Contracts\EventDispatcher\Event;

class UserPasswordChangeEvent extends Event
{
    const NAME  = 'netbs.secure.event.user_password_change';

    private $user;

    private $newPassword;

    public function __construct(BaseUser $user, $newPassword)
    {
        $this->user         = $user;
        $this->newPassword  = $newPassword;
    }

    /**
     * @return string
     */
    public function getNewPassword()
    {
        return $this->newPassword;
    }

    /**
     * @return BaseUser
     */
    public function getUser()
    {
        return $this->user;
    }
}
