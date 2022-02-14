<?php

namespace NetBS\SecureBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\SecureBundle\Form\AutorisationType;
use NetBS\SecureBundle\Service\SecureConfig;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @package NetBS\SecureBundle\Controller
 */
class AutorisationController extends AbstractController
{
    /**
     * @Route("/autorisation/list", name="netbs.secure.autorisation.list")
     */
    public function listAutorisationsAction() {
        return $this->render('@NetBSSecure/autorisation/list.html.twig');
    }

    /**
     * @Route("/autorisation/add", name="netbs.secure.autorisation.add")
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function addUserAction(Request $request, SecureConfig $config, EntityManagerInterface $em) {
        $autr       = $config->createAutorisation();
        $form       = $this->createForm(AutorisationType::class, $autr);

        $form->handleRequest($request);
        if($form->isSubmitted() && $form->isValid()) {

            $em->persist($form->getData());
            $em->flush();

            $this->addFlash("success", "Autorisation pour {$autr->getUser()->getUsername()} ajoutée!");
            return $this->redirectToRoute("netbs.secure.autorisation.list");
        }

        return $this->render('@NetBSCore/generic/form.generic.twig', array(
            'header'    => 'Nouvelle autorisation',
            'subHeader' => "Ajouter une autorisation spéciale sur un groupe à un utilisateur",
            'form'  => $form->createView()
        ));
    }

    /**
     * @param $id
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @Route("/autorisation/delete/{id}", name="netbs.secure.autorisation.delete")
     */
    public function deleteAutorisationAction($id, SecureConfig $secureConfig, EntityManagerInterface $em) {
        $autorisation   = $em->find($secureConfig->getAutorisationClass(), $id);

        try {
            $em->remove($autorisation);
            $em->flush();
        } catch (\Exception $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('netbs.secure.autorisation.list');
    }
}
