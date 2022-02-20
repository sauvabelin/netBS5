<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\FichierBundle\Mapping\BaseFamille;
use App\Entity\BSUser;
use App\Form\CirculaireMembreType;
use App\Model\CirculaireMembre;
use NetBS\FichierBundle\Select2\FamilleProvider;
use NetBS\FichierBundle\Service\FichierConfig;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MembreController extends AbstractController
{
    /**
     * @param Request $request
     * @Route("/membre/nouveau", name="sauvabelin.membre.add_membre")
     * @return Response
     */
    public function pageAddMembreAction(Request $request, FichierConfig $config, EntityManagerInterface $em) {

        /** @var BSUser $user */
        $user               = $this->getUser();
        $infos              = new CirculaireMembre();
        $circuMembre        = $request->request->get('circulaire_membre');
        $selectedFamilyId   = $circuMembre ? $circuMembre['familleId'] : null;
        $previousNumber     = $em->createQueryBuilder()
            ->select('m')->from($config->getMembreClass(), 'm')
            ->orderBy('m.numeroBS', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleResult()
            ->getNumeroBS();

        $options = ['validation_groups' => $selectedFamilyId ? ['default'] : ['default', 'noFamily']];
        $infos->numero      = $previousNumber + 1;
        $form               = $this->createForm(CirculaireMembreType::class, $infos, $options);
        $form->handleRequest($request);

        if(!empty($selectedFamilyId)) {
            $infos->famille = $em->find($config->getFamilleClass(), intval($selectedFamilyId));
        }
        else $infos->generateFamille();

        if($form->isSubmitted() && $form->isValid()) {

            $membre     = $infos->getMembre();
            $famille    = $infos->generateFamille();

            if($user->hasRole('ROLE_SG'))
                $famille->setValidity(BaseFamille::VALIDE);

            $famille->addMembre($membre);

            $em->persist($famille);
            $em->flush();

            return $this->redirect($this->generateUrl('netbs.fichier.membre.page_membre', array('id' => $membre->getId())));
        }

        return $this->render('membre/nouveau.html.twig', array(
            'form'              => $form->createView()
        ));
    }

    /**
     * @param Request $request
     * @Route("/search", name="sauvabelin.famille.search")
     * @return Response
     */
    public function searchFamilleAction(Request $request, FamilleProvider $provider) {

        $term       = $request->get('term');
        $results    = $provider->search($term, 10);
        $serializer = $this->get('serializer');

        $response   = new Response($serializer->serialize($results, 'json', array(
            'groups'    => ['default', 'familleMembres', 'familleAdresse', 'familleGeniteurs']
        )));

        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
}


