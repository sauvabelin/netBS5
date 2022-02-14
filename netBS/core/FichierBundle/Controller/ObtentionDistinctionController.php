<?php

namespace NetBS\FichierBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Utils\Modal;
use NetBS\FichierBundle\Form\ObtentionDistinctionType;
use NetBS\FichierBundle\Mapping\BaseObtentionDistinction;
use NetBS\FichierBundle\Service\FichierConfig;
use NetBS\SecureBundle\Voter\CRUD;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/obtention-distinction")
 */
class ObtentionDistinctionController extends AbstractController
{
    protected $config;

    public function __construct(FichierConfig $config)
    {
        $this->config = $config;
    }

    /**
     * @Route("/modal/creation/{membreId}", defaults={"membreId"=null}, name="netbs.fichier.obtention_distinction.modal_creation")
     * @param Request $request
     * @param $membreId
     * @return Response
     */
    public function modalAddAction(Request $request, $membreId, EntityManagerInterface $em) {
        $odClass        = $this->config->getObtentionDistinctionClass();

        /** @var BaseObtentionDistinction $od */
        $od             = new $odClass();
        $membre         = $em->find($this->config->getMembreClass(), $membreId);

        if(!$membre)
            throw $this->createNotFoundException();

        if(!$this->isGranted(CRUD::UPDATE, $membre))
            throw $this->createAccessDeniedException("Accès refusé");

        $od->setMembre($membre);

        $form           = $this->createForm(ObtentionDistinctionType::class, $od);

        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()) {

            $em->persist($form->getData());
            $em->flush();

            $this->addFlash("success", "Distinction ajoutée avec succès");
            return Modal::refresh();
        }

        return $this->render('@NetBSFichier/generic/add_generic.modal.twig', [
            'title' => "Nouvelle distinction",
            'form'  => $form->createView()
        ], Modal::renderModal($form));
    }
}
