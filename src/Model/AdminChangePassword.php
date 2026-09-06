<?php

namespace App\Model;

use NetBS\SecureBundle\Validator\Constraints\StrongPassword;

class AdminChangePassword
{
    #[StrongPassword]
    private ?string $password = null;

    private bool $forceChange = false;

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): void
    {
        $this->password = $password;
    }

    public function isForceChange(): bool
    {
        return $this->forceChange;
    }

    public function setForceChange(bool $forceChange): void
    {
        $this->forceChange = $forceChange;
    }
}
