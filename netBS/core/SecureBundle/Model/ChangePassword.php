<?php

namespace NetBS\SecureBundle\Model;

use NetBS\SecureBundle\Validator\Constraints\StrongPassword;
use Symfony\Component\Security\Core\Validator\Constraints as Assert;

class ChangePassword
{
    #[Assert\UserPassword(message: 'Mot de passe incorrect')]
    protected ?string $oldPassword = null;

    #[StrongPassword]
    protected ?string $newPassword = null;

    /**
     * @param string $oldPassword
     * @return ChangePassword
     */
    public function setOldPassword($oldPassword)
    {
        $this->oldPassword = $oldPassword;
        return $this;
    }

    /**
     * @return string
     */
    public function getOldPassword()
    {
        return $this->oldPassword;
    }

    /**
     * @param string $newPassword
     * @return ChangePassword
     */
    public function setNewPassword($newPassword)
    {
        $this->newPassword = $newPassword;
        return $this;
    }

    /**
     * @return string
     */
    public function getNewPassword()
    {
        return $this->newPassword;
    }
}