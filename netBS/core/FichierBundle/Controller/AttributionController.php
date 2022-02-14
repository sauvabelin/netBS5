<?php

namespace NetBS\FichierBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Utils\Modal;
use NetBS\FichierBundle\Form\AttributionType;
use NetBS\FichierBundle\Mapping\BaseAttribution;
use NetBS\FichierBundle\Service\FichierConfig;
use NetBS\SecureBundle\Voter\CRUD;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/attribution")
 */
class AttributionController extends AbstractController
{
    protected $config;

    public function __construct(FichierConfig $config)
    {
        $this->config = $config;
    }

    /**
     * @Route("/modal/creation/{membreId}", defaults={"membreId"=null}, name="netbs.fichier.attribution.modal_creation")
     * @param Request $request
     * @return Response
     */
    public function modalAddAction(Request $request, $membreId, EntityManagerInterface $em) {

        $attrClass      = $this->config->getAttributionClass();

        /** @var BaseAttribution $attribution */
        $attribution    = new $attrClass();

        if($membreId !== null) {

            $membre     = $em->find($this->config->getMembreClass(), $membreId);

            if(!$membre)
                throw $this->createNotFoundException();

            $attribution->setMembre($membre);
        }

        $form = $this->createForm(AttributionType::class, $attribution);

        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()) {

            $membre = $form->getData()->getMembre();
            if(!$this->isGranted(CRUD::UPDATE, $membre))
                throw $this->createAccessDeniedException("Ajout d'attribution refusé");

            $em->persist($form->getData());
            $em->flush();

            $this->addFlash("success", "Attribution ajoutée avec succès");
            return Modal::refresh();
        }

        return $this->render('@NetBSFichier/attribution/create.modal.twig', [
            'form'  => $form->createView()
        ], Modal::renderModal($form));
    }
}
