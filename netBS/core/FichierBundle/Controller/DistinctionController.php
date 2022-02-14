<?php

namespace NetBS\FichierBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Utils\Modal;
use NetBS\FichierBundle\Form\DistinctionType;
use NetBS\FichierBundle\Mapping\BaseDistinction;
use NetBS\FichierBundle\Service\FichierConfig;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class DistinctionController
 * @Route("/distinction")
 */
class DistinctionController extends AbstractController
{
    protected $config;

    public function __construct(FichierConfig $config)
    {
        $this->config = $config;
    }

    /**
     * @Route("/manage", name="netbs.fichier.distinction.page_distinctions")
     * @Security("is_granted('ROLE_READ_EVERYWHERE')")
     */
    public function pageDistinctionsAction(RouterInterface $router) {

        return $this->render('@NetBSFichier/generic/page_generic.html.twig', array(
            'list'      => 'netbs.fichier.distinctions',
            'title'     => 'Distinctions',
            'subtitle'  => 'Toutes les distinctions enregistrées',
            'modalPath' => $router->generate('netbs.fichier.distinction.modal_add')
        ));
    }

    /**
     * @param Request $request
     * @return Response
     * @Route("/modal/add", name="netbs.fichier.distinction.modal_add")
     * @Security("is_granted('ROLE_CREATE_EVERYWHERE')")
     */
    public function addDistinctionModalAction(Request $request, EntityManagerInterface $em) {

        $distClass      = $this->config->getDistinctionClass();

        /** @var BaseDistinction $distinction */
        $distinction    = new $distClass();
        $form           = $this->createForm(DistinctionType::class, $distinction);

        $form->handleRequest($request);
        if($form->isValid() && $form->isSubmitted()) {
            $em->persist($form->getData());
            $em->flush();

            $this->addFlash('success', "La distinction {$distinction->getNom()} a été ajoutée");
            return Modal::refresh();
        }

        return $this->render('@NetBSFichier/generic/add_generic.modal.twig', [
            'form'  => $form->createView()
        ], Modal::renderModal($form));
    }
}

