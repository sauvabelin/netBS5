<?php

namespace NetBS\FichierBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Utils\Modal;
use NetBS\FichierBundle\Form\GroupeCategorieType;
use NetBS\FichierBundle\Service\FichierConfig;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class DistinctionController
 * @Route("/groupe-categorie")
 */
class GroupeCategorieController extends AbstractController
{
    protected $config;

    public function __construct(FichierConfig $config)
    {
        $this->config = $config;
    }

    /**
     * @Route("/manage", name="netbs.fichier.groupe_categorie.page_groupe_categories")
     * @Security("is_granted('ROLE_READ_EVERYWHERE')")
     */
    public function pageGroupeCategorieAction() {

        return $this->render('@NetBSFichier/generic/page_generic.html.twig', array(
            'list'      => 'netbs.fichier.groupe_categories',
            'title'     => "Catégories d'unités",
            'subtitle'  => 'Toutes les catégories enregistrés',
            'modalPath' => $this->get('router')->generate('netbs.fichier.groupe_categorie.modal_add')
        ));
    }

    /**
     * @param Request $request
     * @Route("/modal/add", name="netbs.fichier.groupe_categorie.modal_add")
     * @return \Symfony\Component\HttpFoundation\Response
     * @Security("is_granted('ROLE_CREATE_EVERYWHERE')")
     */
    public function addGroupeCategorieModalAction(Request $request, EntityManagerInterface $em) {

        $class          = $this->config->getGroupeCategorieClass();
        $gcategorie     = new $class();
        $form           = $this->createForm(GroupeCategorieType::class, $gcategorie);

        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()) {
            $em->persist($form->getData());
            $em->flush();

            $this->addFlash("success", "Catégorie de groupe ajoutée");
            return Modal::refresh();
        }

        return $this->render('@NetBSFichier/generic/add_generic.modal.twig', [
            'form'  => $form->createView()
        ], Modal::renderModal($form));
    }
}
