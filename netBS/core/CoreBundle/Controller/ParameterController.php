<?php

namespace NetBS\CoreBundle\Controller;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class ParameterController extends AbstractController
{
    /**
     * @Route("/parameters/list", name="netbs.core.parameters.list")
     */
    public function listParametersAction()
    {
        return $this->render('@NetBSCore/parameters/list_parameters.html.twig');
    }
}
