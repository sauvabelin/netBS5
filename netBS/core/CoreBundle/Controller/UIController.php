<?php

namespace NetBS\CoreBundle\Controller;

use NetBS\FichierBundle\Mapping\BaseFamille;
use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\FichierBundle\Select2\FamilleProvider;
use NetBS\FichierBundle\Select2\GroupeProvider;
use NetBS\FichierBundle\Select2\MembreProvider;
use NetBS\SecureBundle\Voter\CRUD;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class UIController extends AbstractController
{
    /**
     * @Route("/ui/global-search", name="netbs.core.ui.global_search")
     * @param Request $request
     * @return JsonResponse
     */
    public function globalSearchAction(Request $request, MembreProvider $membreProvider, GroupeProvider $groupeProvider, FamilleProvider $familleProvider)
    {
        $term           = $request->get('query');
        $router         = $this->get('router');
        $membres        = $membreProvider->search($term, 20);
        $groupes        = $groupeProvider->search($term, 20);
        $familles       = $familleProvider->search($term, 20);
        $results        = [];

        /** @var BaseMembre $membre */
        foreach($membres as $membre) {

            if(count($results) > 4) break;
            if(!$this->isGranted(CRUD::READ, $membre))
                continue;

            $descr      = '';
            if($attr = $membre->getActiveAttribution())
                $descr  = $attr->__toString();

            $results[] = [
                'name'          => $membre->getFullName(),
                'description'   => $descr,
                'path'          => $router->generate('netbs.fichier.membre.page_membre', ['id' => $membre->getId()])
            ];
        }

        /** @var BaseFamille $famille */
        foreach($familles as $famille) {
            if(count($results) > 9) break;
            if(!$this->isGranted(CRUD::READ, $famille))
                continue;

            $descr  = '';
            if($adresse = $famille->getSendableAdresse())
                $descr = $adresse->getNpa() . ' ' . $adresse->getLocalite();

            $results[] = [
                'name'          => $famille->__toString(),
                'description'   => $descr,
                'path'          => $router->generate('netbs.fichier.famille.page_famille', ['id' => $famille->getId()])
            ];
        }

        /** @var BaseGroupe $groupe */
        foreach($groupes as $groupe) {

            if(count($results) > 14) break;
            if(!$this->isGranted(CRUD::READ, $groupe))
                continue;

            $results[] = [
                'name'          => $groupe->getNom(),
                'description'   => $groupe->getGroupeType()->getNom(),
                'path'          => $router->generate('netbs.fichier.groupe.page_groupe', ['id' => $groupe->getId()])
            ];
        }

        return new JsonResponse($results);
    }
}
