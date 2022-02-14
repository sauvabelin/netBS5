<?php

namespace NetBS\CoreBundle\Select2;

class Select2ProviderManager
{
    /**
     * @var Select2ProviderInterface[]
     */
    protected $providers = [];

    public function registerProvider(Select2ProviderInterface $provider) {

        $this->providers[]  = $provider;
    }

    public function getProvider($class) {

        foreach($this->providers as $provider)
            if($provider->getManagedClass() == $class)
                return $provider;

        throw new \Exception("No select2 provider found for the class $class.");
    }

    public function getProviders() {

        return $this->providers;
    }
}