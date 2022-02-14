<?php

namespace NetBS\FichierBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Utils\Modal;
use NetBS\FichierBundle\Form\GroupeTypeType;
use NetBS\FichierBundle\Service\FichierConfig;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/groupe-type")
 */
class GroupeTypeController extends AbstractController
{
    protected $config;

    public function __construct(FichierConfig $config)
    {
        $this->config = $config;
    }

    /**
     * @Route("/manage", name="netbs.fichier.groupe_type.page_groupe_types")
     * @Security("is_granted('ROLE_READ_EVERYWHERE')")
     */
    public function pageGroupeTypesAction() {

        return $this->render('@NetBSFichier/generic/page_generic.html.twig', array(
            'list'      => 'netbs.fichier.groupe_types',
            'title'     => "Types de groupe",
            'subtitle'  => 'Tous les types enregistrés',
            'modalPath' => $this->get('router')->generate('netbs.fichier.groupe_type.modal_add')
        ));
    }

    /**
     * @param Request $request
     * @Route("/modal/add", name="netbs.fichier.groupe_type.modal_add")
     * @return \Symfony\Component\HttpFoundation\Response
     * @Security("is_granted('ROLE_CREATE_EVERYWHERE')")
     */
    public function addGroupeTypeModalAction(Request $request, EntityManagerInterface $em) {

        $class          = $this->config->getGroupeTypeClass();
        $gtype          = new $class();
        $form           = $this->createForm(GroupeTypeType::class, $gtype);

        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()) {
            $em->persist($form->getData());
            $em->flush();

            $this->addFlash('success', "Type de groupe ajouté");
            return Modal::refresh();
        }

        return $this->render('@NetBSFichier/generic/add_generic.modal.twig', [
            'form'  => $form->createView(),
            'title' => 'Nouveau type de groupe'
        ], Modal::renderModal($form));
    }
}
