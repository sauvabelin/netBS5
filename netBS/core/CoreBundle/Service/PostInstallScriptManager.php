<?php

namespace NetBS\CoreBundle\Service;

use Symfony\Component\Console\Command\Command;

class PostInstallScriptManager
{
    /**
     * @var Command[]
     */
    private $scripts    = [];

    public function registerScript(Command $command) {
        $this->scripts[]    = $command;
    }

    public function getScripts() {
        return $this->scripts;
    }
}