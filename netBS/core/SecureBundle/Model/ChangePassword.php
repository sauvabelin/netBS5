<?php

namespace NetBS\SecureBundle\Model;

use NetBS\SecureBundle\Validator\Constraints\StrongPassword;
use Symfony\Component\Security\Core\Validator\Constraints as Assert;

class ChangePassword
{
    // Only validated when the form actually collects the current password
    // (require_current). UserPasswordValidator flags a null/empty value as
    // invalid outright, so without this group the reset flow — which never
    // sets oldPassword — would always fail. ChangePasswordType selects the
    // group from require_current.
    #[Assert\UserPassword(message: 'Mot de passe incorrect', groups: ['current_password'])]
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