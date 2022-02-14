<?php

namespace NetBS\CoreBundle\Service;

use NetBS\CoreBundle\Model\LoaderInterface;

class LoaderManager
{
    private $loaders;

    public function registerLoader(LoaderInterface $loader) {
        $this->loaders[$loader->getLoadableClass()] = $loader;
    }

    public function hasLoader($class) {
        return isset($this->loaders[$class]);
    }

    /**
     * @param $class
     * @return LoaderInterface
     * @throws \Exception
     */
    public function getLoader($class) {
        if (!$this->hasLoader($class))
            throw new \Exception("No loader exist for class " . $class);

        return $this->loaders[$class];
    }
}
