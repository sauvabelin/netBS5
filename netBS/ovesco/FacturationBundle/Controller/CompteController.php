<?php

namespace Ovesco\FacturationBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Utils\Modal;
use Ovesco\FacturationBundle\Entity\Compte;
use Ovesco\FacturationBundle\Form\CompteType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class CompteController
 * @package Ovesco\FacturationBundle\Controller
 * @Route("/compte")
 */
class CompteController extends AbstractController
{
    /**
     * @Route("/list", name="ovesco.facturation.compte.list")
     */
    public function listAccountsAction(RouterInterface $router) {

        return $this->render('@NetBSFichier/generic/page_generic.html.twig', array(
            'list'      => 'facturation.accounts',
            'title'     => 'Comptes BVR',
            'subtitle'  => 'Tous les comptes banquaires utilisables pour BVR',
            'modalPath' => $router->generate('ovesco.facturation.compte.modal_add')
        ));
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Exception
     * @Route("/modal/add", name="ovesco.facturation.compte.modal_add")
     */
    public function addAccountModalAction(Request $request, EntityManagerInterface $em) {

        $account = new Compte();
        $form = $this->createForm(CompteType::class, $account);

        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()) {

            $em->persist($form->getData());
            $em->flush();

            return Modal::refresh();
        }

        return $this->render('@NetBSFichier/generic/add_generic.modal.twig', array(
            'form'  => $form->createView()
        ), Modal::renderModal($form));
    }
}
