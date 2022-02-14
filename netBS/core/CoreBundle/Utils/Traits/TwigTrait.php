<?php

namespace NetBS\CoreBundle\Utils\Traits;

use Twig\Environment;

trait TwigTrait
{
    /**
     * @var Environment
     */
    protected $twig;

    public function setTwig(Environment $twig_Environment) {

        $this->twig = $twig_Environment;
    }
}
