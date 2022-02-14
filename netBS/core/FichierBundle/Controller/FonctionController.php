<?php

namespace NetBS\FichierBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Utils\Modal;
use NetBS\FichierBundle\Form\FonctionType;
use NetBS\FichierBundle\Mapping\BaseFonction;
use NetBS\FichierBundle\Service\FichierConfig;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class DistinctionController
 * @Route("/fonction")
 */
class FonctionController extends AbstractController
{
    protected $config;

    public function __construct(FichierConfig $config)
    {
        $this->config = $config;
    }

    /**
     * @Route("/manage", name="netbs.fichier.fonction.page_fonctions")
     * @Security("is_granted('ROLE_READ_EVERYWHERE')")
     */
    public function pageFonctionsAction() {

        return $this->render('@NetBSFichier/generic/page_generic.html.twig', array(
            'list'      => 'netbs.fichier.fonctions',
            'title'     => "Fonctions",
            'subtitle'  => 'Fonctions existantes et enregistrées',
            'modalPath' => $this->get('router')->generate('netbs.fichier.fonction.modal_add')
        ));
    }

    /**
     * @param Request $request
     * @Route("/modal/add", name="netbs.fichier.fonction.modal_add")
     * @return \Symfony\Component\HttpFoundation\Response
     * @Security("is_granted('ROLE_CREATE_EVERYWHERE')")
     */
    public function addFonctionModalAction(Request $request, EntityManagerInterface $em) {

        $class          = $this->config->getFonctionClass();

        /** @var BaseFonction $fonction */
        $fonction       = new $class();
        $form           = $this->createForm(FonctionType::class, $fonction);

        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()) {
            $em->persist($form->getData());
            $em->flush();

            $this->addFlash('success', "La fonction {$fonction->getNom()} a été ajoutée");
            return Modal::refresh();
        }

        return $this->render('@NetBSFichier/generic/add_generic.modal.twig', [
            'form'  => $form->createView()
        ], Modal::renderModal($form));
    }
}
