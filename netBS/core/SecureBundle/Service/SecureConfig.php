<?php

namespace NetBS\SecureBundle\Service;

use NetBS\SecureBundle\Mapping\BaseAutorisation;
use NetBS\SecureBundle\Mapping\BaseUser;
use NetBS\SecureBundle\Mapping\BaseRole;

class SecureConfig
{
    /**
     * @var array
     */
    protected $config;

    public function __construct(array $config)
    {
        if(!is_subclass_of($config['entities']['user_class'], BaseUser::class))
            throw new \Exception("redefined user class must extend " . BaseUser::class);

        if(!is_subclass_of($config['entities']['role_class'], BaseRole::class))
            throw new \Exception("redefined role class must extend " . BaseRole::class);

        if(!is_subclass_of($config['entities']['autorisation_class'], BaseAutorisation::class))
            throw new \Exception("redefined autorisation class must extend " . BaseAutorisation::class);

        $this->config   = $config;
    }

    public function getUserClass() {
        return $this->config['entities']['user_class'];
    }

    public function getRoleClass() {
        return $this->config['entities']['role_class'];
    }

    public function getAutorisationClass() {
        return $this->config['entities']['autorisation_class'];
    }

    /**
     * @return BaseAutorisation
     */
    public function createAutorisation() {

        $class  = $this->getAutorisationClass();
        return new $class();
    }

    /**
     * @return BaseUser
     */
    public function createUser() {

        $class  = $this->getUserClass();
        return new $class();
    }

    /**
     * @return BaseRole
     */
    public function createRole() {

        $class  = $this->getRoleClass();
        return new $class();
    }
}
