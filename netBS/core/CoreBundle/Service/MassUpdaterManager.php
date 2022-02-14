<?php

namespace NetBS\CoreBundle\Service;

use NetBS\CoreBundle\Model\BaseMassUpdater;

class MassUpdaterManager
{
    /**
     * @var BaseMassUpdater[]
     */
    protected $updaters = [];

    public function registerUpdater(BaseMassUpdater $updater) {

        $this->updaters[$updater->getUpdatedItemClass()]   = $updater;
    }

    public function getUpdaterForClass($class) {

        if(isset($this->updaters[$class]))
            return $this->updaters[$class];
    }

    public function getUpdaters() {

        return $this->updaters;
    }
}