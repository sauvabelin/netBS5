<?php

namespace NetBS\CoreBundle\Service;

use NetBS\CoreBundle\Model\BaseDeleter;

class DeleterManager
{
    protected $deleters = [];

    public function registerDeleter(BaseDeleter $deleter) {
        $this->deleters[$deleter->getManagedClass()] = $deleter;
    }

    /**
     * @param $class
     * @return BaseDeleter|null
     */
    public function getDeleter($class) {
        if (isset($this->deleters[$class]))
            return $this->deleters[$class];
        return null;
    }
}
