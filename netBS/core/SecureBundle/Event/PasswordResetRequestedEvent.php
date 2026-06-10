<?php

namespace NetBS\SecureBundle\Event;

use App\Entity\BSUser;
use Symfony\Contracts\EventDispatcher\Event;

class PasswordResetRequestedEvent extends Event
{
    public const NAME = 'netbs.security.password_reset.requested';

    public function __construct(
        private readonly BSUser $user,
        private readonly ?string $ip,
    ) {}

    public function getUser(): BSUser
    {
        return $this->user;
    }

    public function getIp(): ?string
    {
        return $this->ip;
    }
}
