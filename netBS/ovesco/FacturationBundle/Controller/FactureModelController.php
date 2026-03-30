<?php

namespace Ovesco\FacturationBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Utils\Modal;
use Ovesco\FacturationBundle\Entity\FactureModel;
use Ovesco\FacturationBundle\Form\FactureModelType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class CreanceController
 * @package Ovesco\FacturationBundle\Controller
 * @Route("/facture-model")
 */
class FactureModelController extends AbstractController
{
    /**
     * @Route("/list", name="ovesco.facturation.facture_model.list")
     */

    public function listAction(RouterInterface $router) {
        return $this->render('@NetBSFichier/generic/page_generic.html.twig', [
            'title' => 'Modèles de facture',
            'subtitle' => "Tous les modèles de facture enregistrés et utilisables",
            'list' => 'ovesco.facturation.facture_models',
            'modalPath' => $router->generate('ovesco.facturation.facture_model.add_modal'),
            'doc' => '@OvescoFacturation/doc/facture_models.twig',
        ]);
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @Route("/add-modal", name="ovesco.facturation.facture_model.add_modal")
     */
    public function addModalAction(Request $request, EntityManagerInterface $em) {

        $model = new FactureModel();
        $form = $this->createForm(FactureModelType::class, $model);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {

            $em->persist($model);
            $em->flush();
            $this->addFlash('success', "Modèle de facture ajouté!");
            return Modal::refresh();
        }

        return $this->render('@OvescoFacturation/model/add_facture_model.modal.twig', [
            'form' => $form->createView(),
        ], Modal::renderModal($form));
    }

    /**
     * @Route("/edit-modal/{id}", name="ovesco.facturation.facture_model.edit_modal")
     * @param FactureModel $model
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function editModalAction(FactureModel $model, Request $request, EntityManagerInterface $em) {

        $form = $this->createForm(FactureModelType::class, $model);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {

            $em->persist($model);
            $em->flush();
            $this->addFlash('success', "Modèle de facture mis à jour!");
            return Modal::refresh();
        }

        return $this->render('@OvescoFacturation/model/add_facture_model.modal.twig', [
            'form' => $form->createView(),
        ], Modal::renderModal($form));
    }

    /**
     * @Route("/duplicate/{id}", name="ovesco.facturation.facture_model.duplicate")
     */
    public function duplicateModalAction(FactureModel $model, Request $request, EntityManagerInterface $em) {

        $this->denyAccessUnlessGranted('create', $model);

        $duplicate = new FactureModel();
        $duplicate->setName($model->getName() . ' (copie)');
        $duplicate->setTitre($model->getTitre());
        $duplicate->setTopDescription($model->getTopDescription());
        $duplicate->setBottomSalutations($model->getBottomSalutations());
        $duplicate->setSignataire($model->getSignataire());
        $duplicate->setGroupName($model->getGroupName());
        $duplicate->setRue($model->getRue());
        $duplicate->setNpaVille($model->getNpaVille());
        $duplicate->setCityFrom($model->getCityFrom());
        $duplicate->setPoids($model->getPoids());
        $duplicate->setApplicationRule($model->getApplicationRule());

        $form = $this->createForm(FactureModelType::class, $duplicate);
        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {

            $em->persist($duplicate);
            $em->flush();
            $this->addFlash('success', "Modèle de facture dupliqué!");
            return Modal::refresh();
        }

        return $this->render('@OvescoFacturation/model/add_facture_model.modal.twig', [
            'form' => $form->createView(),
        ], Modal::renderModal($form));
    }
}
