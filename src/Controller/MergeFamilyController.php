<?php

namespace App\Controller;

use NetBS\FichierBundle\Service\FichierConfig;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @package App\Controller
 * @Route("/merge-family")
 */
class MergeFamilyController extends AbstractController
{
    /**
     * @Route("/merger", name="sauvabelin.merge_family.merger")
     */
    public function mergerAction(FichierConfig $config) {

        $repo = $this->getDoctrine()->getRepository($config->getFamilleClass());
        return $this->render('mergeFamily/merger.html.twig', [
            'familles' => $repo->findAll()
        ]);
    }

    /**
     * @Route("/choose-what", name="sauvabelin.merge_family.choose_what")
     */
    public function chooseWhatAction(Request $request, FichierConfig $config) {

        $repo = $this->get('doctrine.orm.default_entity_manager')->getRepository($config->getFamilleClass());
        $familles = array_map(function($id) use ($repo) {
            return $repo->find($id);
        }, $request->request->get('famille'));

        return $this->render('mergeFamily/choose_what.html.twig', [
            'familles' => $familles,
        ]);
    }
}
