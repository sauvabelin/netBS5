<?php

namespace NetBS\SecureBundle\Event;

use App\Entity\BSUser;
use Symfony\Contracts\EventDispatcher\Event;

class PasswordResetCompletedEvent extends Event
{
    public const NAME = 'netbs.security.password_reset.completed';

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
