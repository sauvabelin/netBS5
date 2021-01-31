<?php

namespace App\Model;

class AdminChangePassword
{
    /**
     * @var string
     */
    private $password;

    /**
     * @var boolean
     */
    private $forceChange;

    /**
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    /**
     * @return bool
     */
    public function isForceChange()
    {
        return $this->forceChange;
    }

    /**
     * @param bool $forceChange
     */
    public function setForceChange($forceChange)
    {
        $this->forceChange = $forceChange;
    }
}
