<?php

namespace NetBS\CoreBundle\ListModel;

class ActionItem
{
    private $actionClass;

    private $actionParams;

    public function __construct($actionClass, $actionParams = [])
    {
        $this->actionClass  = $actionClass;
        $this->actionParams = $actionParams;
    }

    /**
     * @return string
     */
    public function getActionClass()
    {
        return $this->actionClass;
    }

    /**
     * @return array
     */
    public function getActionParams()
    {
        return $this->actionParams;
    }
}