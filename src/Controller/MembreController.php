<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Controller\Trait\HandlesFormPersistenceTrait;
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
use Symfony\Component\Serializer\SerializerInterface;

class MembreController extends AbstractController
{
    use HandlesFormPersistenceTrait;

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/membre/nouveau', name: 'sauvabelin.membre.add_membre')]
    public function pageAddMembreAction(Request $request, FichierConfig $config, EntityManagerInterface $em) {

        /** @var BSUser $user */
        $user               = $this->getUser();
        $infos              = new CirculaireMembre();
        $circuMembre        = $request->request->all('circulaire_membre') ?: null;
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
        // Resolve famille BEFORE handleRequest so the group sequence ('noFamily')
        // sees the right state for validation. On the failure path we must NOT
        // call generateFamille() — it mutates $infos->famille and would skip the
        // 'noFamily' validation group on the next submit, hiding required-field errors.
        if(!empty($selectedFamilyId)) {
            $infos->famille = $em->find($config->getFamilleClass(), intval($selectedFamilyId));
        }

        $form               = $this->createForm(CirculaireMembreType::class, $infos, $options);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {

            $membre     = $infos->getMembre();
            $famille    = $infos->generateFamille();

            if($user->hasRole('ROLE_SG'))
                $famille->setValidity(BaseFamille::VALIDE);

            $famille->addMembre($membre);

            $em->persist($famille);

            // DB constraint violations (e.g. UNIQUE on the auto-generated
            // BSUser.username) turn into a form-level error and the response
            // becomes 422 — the form re-renders with input intact and the
            // alert banner explains what went wrong.
            if ($this->flushOrAttachConstraintError($em, $form, $request)) {
                return $this->redirect($this->generateUrl('netbs.fichier.membre.page_membre', array('id' => $membre->getId())));
            }
        }

        return $this->render('membre/nouveau.html.twig', array(
            'form'              => $form->createView()
        ));
    }

    /**
     * @param Request $request
     * @return Response
     */
    #[Route('/search', name: 'sauvabelin.famille.search')]
    public function searchFamilleAction(Request $request, FamilleProvider $provider, SerializerInterface $serializer) {

        $term       = $request->get('term');
        $results    = $provider->search($term, 10);

        $response   = new Response($serializer->serialize($results, 'json', array(
            'groups'    => ['default', 'familleMembres', 'familleAdresse', 'familleGeniteurs']
        )));

        $response->headers->set('Content-Type', 'application/json');
        return $response;
    }
}


