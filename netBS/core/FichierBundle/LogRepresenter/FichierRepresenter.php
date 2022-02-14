<?php

namespace NetBS\FichierBundle\LogRepresenter;

use NetBS\CoreBundle\Model\LogRepresenterInterface;
use NetBS\FichierBundle\Service\FichierConfig;
use Twig\Environment;

abstract class FichierRepresenter implements LogRepresenterInterface
{
    /**
     * @var FichierConfig
     */
    protected $config;

    /**
     * @var Environment
     */
    protected $twig;

    public function setConfig(FichierConfig $config) {

        $this->config   = $config;
    }

    public function setTwig(Environment $twig) {

        $this->twig = $twig;
    }
}
