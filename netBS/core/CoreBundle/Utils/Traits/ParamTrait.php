<?php

namespace NetBS\CoreBundle\Utils\Traits;

use NetBS\CoreBundle\Service\ParameterManager;

trait ParamTrait
{
    /**
     * @var ParameterManager
     */
    protected $parameterManager;

    public function setParameterManager(ParameterManager $parameterManager) {

        $this->parameterManager = $parameterManager;
    }
}