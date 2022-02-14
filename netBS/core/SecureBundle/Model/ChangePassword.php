<?php

namespace NetBS\SecureBundle\Model;

use Symfony\Component\Security\Core\Validator\Constraints as Assert;

class ChangePassword
{
    /**
     * @var string
     * @Assert\UserPassword(message = "Mot de passe incorrect")
     */
    protected $oldPassword;

    /**
     * @var string
     */
    protected $newPassword;

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