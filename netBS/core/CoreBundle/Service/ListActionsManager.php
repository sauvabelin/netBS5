<?php

namespace NetBS\CoreBundle\Service;

use NetBS\CoreBundle\ListModel\Action\ActionInterface;

class ListActionsManager
{
    private $actions;

    public function registerAction(ActionInterface $action) {

        $this->actions[get_class($action)] = $action;
    }

    /**
     * @param $class
     * @return ActionInterface
     * @throws \Exception
     */
    public function getAction($class) {

        if(!isset($this->actions[$class]))
            throw new \Exception("No action registered with class $class");
        
        return $this->actions[$class];
    }
}