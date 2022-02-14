<?php

namespace NetBS\FichierBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Service\History;
use NetBS\CoreBundle\Utils\Modal;
use NetBS\FichierBundle\Form\Contact\TelephoneType;
use NetBS\FichierBundle\Service\FichierConfig;
use NetBS\SecureBundle\Voter\CRUD;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @package FichierBundle\Controller
 * @Route("/telephone")
 */
class TelephoneController extends AbstractController
{
    protected $config;

    public function __construct(FichierConfig $config)
    {
        $this->config = $config;
    }

    /**
     * @Route("/delete/{ownerType}/{ownerId}/{telephoneId}", name="netbs.fichier.telephone.delete")
     * @param $ownerType
     * @param $ownerId
     * @param $telephoneId
     * @return Response
     */
    public function deleteTelephoneAction($ownerType, $ownerId, $telephoneId, EntityManagerInterface $em, History $history) {

        $class  = $this->config->getTelephoneClass();
        $owner  = $em->getRepository(base64_decode($ownerType))->find($ownerId);
        $tel    = $em->getRepository($class)->find($telephoneId);

        if(!$this->isGranted(CRUD::DELETE, $tel))
            throw $this->createAccessDeniedException("Suppression du numéro de téléphone refusée");

        $owner->removeTelephone($tel);
        $em->remove($tel);
        $em->flush();

        $this->addFlash("info", "Numéro " . $tel->getTelephone() . " correctement supprimé");
        return $history->getPreviousRoute();
    }

    /**
     * @Route("/modal/creation/{ownerType}/{ownerId}", name="netbs.fichier.telephone.modal_creation")
     * @return Response
     */
    public function modalAddAction($ownerType, $ownerId, Request $request, EntityManagerInterface $em) {

        $class  = $this->config->getTelephoneClass();
        $form   = $this->createForm(TelephoneType::class, new $class());

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $holder = $em->getRepository(base64_decode($ownerType))->find($ownerId);

            if(!$this->isGranted(CRUD::UPDATE, $holder))
                throw $this->createAccessDeniedException("Accès refusé");

            $holder->addTelephone($form->getData());

            $em->persist($holder);
            $em->flush();

            $this->addFlash("success", "Numéro de téléphone ajouté avec succès");
            return Modal::refresh();
        }

        return $this->render('@NetBSFichier/telephone/add_telephone.modal.twig', [
            'form'  => $form->createView()
        ], Modal::renderModal($form));
    }
}
