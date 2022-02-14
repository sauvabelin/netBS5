<?php

namespace NetBS\CoreBundle\Controller;

use NetBS\CoreBundle\Block\LayoutManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    /**
     * @Route("/", name="netbs.core.home.dashboard")
     */
    public function indexAction(LayoutManager $layout)
    {
        $config     = $layout::configurator();

        return $layout->renderResponse('netbs', $config, [
            'title' => "Accueil"
        ]);
    }
}
