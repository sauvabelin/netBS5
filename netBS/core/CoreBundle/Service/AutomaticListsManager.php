<?php

namespace NetBS\CoreBundle\Service;

use NetBS\CoreBundle\Model\BaseAutomatic;
use NetBS\ListBundle\Model\ListModelInterface;

class AutomaticListsManager
{
    /**
     * @var BaseAutomatic[]
     */
    protected $automatics   = [];

    public function registerAutomatic(ListModelInterface $model) {

        $this->automatics[$model->getAlias()] = $model;
    }

    public function getAutomatics() {

        return $this->automatics;
    }

    /**
     * @param $alias
     * @return BaseAutomatic
     */
    public function getAutomaticByAlias($alias) {

        return $this->automatics[$alias];
    }
}