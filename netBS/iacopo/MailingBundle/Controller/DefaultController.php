<?php

namespace Iacopo\MailingBundle\Controller;

use Iacopo\MailingBundle\Entity\MailingList;
use Iacopo\MailingBundle\Entity\MailingListAlias;
use Iacopo\MailingBundle\Entity\MailingTarget;
use Iacopo\MailingBundle\Form\MailingListType;
use Iacopo\MailingBundle\Form\MailingListAliasType;
use Iacopo\MailingBundle\Form\MailingTargetType;
use Iacopo\MailingBundle\ListModel\MailingTargetListModel;
use Iacopo\MailingBundle\ListModel\MailingAliasListModel;
use Iacopo\MailingBundle\Service\MailingTargetResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;

class DefaultController extends AbstractController
{
    private $em;
    private $targetListModel;
    private $aliasListModel;
    private $targetResolver;

    public function __construct(
        EntityManagerInterface $em,
        MailingTargetListModel $targetListModel,
        MailingAliasListModel $aliasListModel,
        MailingTargetResolver $targetResolver
    ) {
        $this->em = $em;
        $this->targetListModel = $targetListModel;
        $this->aliasListModel = $aliasListModel;
        $this->targetResolver = $targetResolver;
    }

    /**
     * @Route("/", name="iacopo.mailing.list")
     */
    public function indexAction(): Response
    {
        return $this->render('@IacopoMailing/default/index.html.twig');
    }

    /**
     * @Route("/create", name="iacopo.mailing.create")
     */
    public function createAction(Request $request): Response
    {
        $mailingList = new MailingList();
        $form = $this->createForm(MailingListType::class, $mailingList);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($mailingList);
            $this->em->flush();

            $this->addFlash('success', 'Liste de diffusion créée avec succès');

            // Redirect to edit page of newly created mailing list
            return $this->redirectToRoute('iacopo.mailing.edit', ['id' => $mailingList->getId()]);
        }

        return $this->render('@IacopoMailing/default/create.modal.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/edit/{id}", name="iacopo.mailing.edit")
     */
    public function editAction(int $id, Request $request): Response
    {
        $mailingList = $this->em->getRepository(MailingList::class)->find($id);

        if (!$mailingList) {
            throw $this->createNotFoundException('Liste non trouvée');
        }

        // Form for editing the mailing list itself
        $listForm = $this->createForm(MailingListType::class, $mailingList);
        $listForm->handleRequest($request);

        if ($listForm->isSubmitted() && $listForm->isValid()) {
            $mailingList->setUpdatedAt(new \DateTime());
            $this->em->flush();

            $this->addFlash('success', 'Liste mise à jour avec succès');

            return $this->redirectToRoute('iacopo.mailing.edit', ['id' => $id]);
        }

        // Get the last used type from query parameter
        $lastType = $request->query->get('lastType', MailingTarget::TYPE_EMAIL);

        // Form for adding new target
        $newTarget = new MailingTarget();
        $newTarget->setMailingList($mailingList);
        $newTarget->setType($lastType);
        $targetForm = $this->createForm(MailingTargetType::class, $newTarget);

        $targetForm->handleRequest($request);

        if ($targetForm->isSubmitted() && $targetForm->isValid()) {
            $this->em->persist($newTarget);
            $this->em->flush();

            $this->addFlash('success', 'Destinataire ajouté avec succès');

            return $this->redirectToRoute('iacopo.mailing.edit', [
                'id' => $id,
                'lastType' => $newTarget->getType()
            ]);
        }

        // Form for adding new alias
        $newAlias = new MailingListAlias();
        $newAlias->setMailingList($mailingList);
        $aliasForm = $this->createForm(MailingListAliasType::class, $newAlias);

        $aliasForm->handleRequest($request);

        if ($aliasForm->isSubmitted() && $aliasForm->isValid()) {
            $this->em->persist($newAlias);
            $this->em->flush();

            $this->addFlash('success', 'Adresse alternative ajoutée avec succès');

            return $this->redirectToRoute('iacopo.mailing.edit', ['id' => $id]);
        }

        // Configure list models
        $this->targetListModel->setMailingListId($id);
        $this->aliasListModel->setMailingListId($id);

        // Calculate recipient count
        $recipientCount = $this->targetResolver->countMailingList($mailingList);

        return $this->render('@IacopoMailing/default/edit.html.twig', [
            'mailingList' => $mailingList,
            'listForm' => $listForm->createView(),
            'targetForm' => $targetForm->createView(),
            'aliasForm' => $aliasForm->createView(),
            'lastType' => $lastType,
            'recipientCount' => $recipientCount
        ]);
    }

    /**
     * @Route("/target/{id}/edit", name="iacopo.mailing.target.edit")
     */
    public function editTargetAction(int $id, Request $request): Response
    {
        $target = $this->em->getRepository(MailingTarget::class)->find($id);

        if (!$target) {
            throw $this->createNotFoundException('Destinataire non trouvé');
        }

        $form = $this->createForm(MailingTargetType::class, $target);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            $this->addFlash('success', 'Destinataire modifié avec succès');

            return $this->redirectToRoute('iacopo.mailing.edit', [
                'id' => $target->getMailingList()->getId(),
                'lastType' => $target->getType()
            ]);
        }

        return $this->render('@IacopoMailing/default/edit_target.modal.twig', [
            'form' => $form->createView(),
            'target' => $target
        ]);
    }

    /**
     * @Route("/{id}/toggle-active", name="iacopo.mailing.toggle_active", methods={"POST"})
     */
    public function toggleActiveAction(int $id): JsonResponse
    {
        $mailingList = $this->em->getRepository(MailingList::class)->find($id);

        if (!$mailingList) {
            return new JsonResponse(['error' => 'Liste non trouvée'], 404);
        }

        $mailingList->setActive(!$mailingList->isActive());
        $this->em->flush();

        return new JsonResponse([
            'success' => true,
            'active' => $mailingList->isActive()
        ]);
    }

    /**
     * @Route("/target/{id}/delete", name="iacopo.mailing.target.delete", methods={"POST"})
     */
    public function deleteTargetAction(int $id): Response
    {
        $target = $this->em->getRepository(MailingTarget::class)->find($id);

        if ($target) {
            $listId = $target->getMailingList()->getId();
            $this->em->remove($target);
            $this->em->flush();

            $this->addFlash('success', 'Destinataire supprimé');

            return $this->redirectToRoute('iacopo.mailing.edit', ['id' => $listId]);
        }

        return $this->redirectToRoute('iacopo.mailing.list');
    }

    /**
     * @Route("/alias/{id}/delete", name="iacopo.mailing.alias.delete", methods={"POST"})
     */
    public function deleteAliasAction(int $id): Response
    {
        $alias = $this->em->getRepository(MailingListAlias::class)->find($id);

        if ($alias) {
            $listId = $alias->getMailingList()->getId();
            $this->em->remove($alias);
            $this->em->flush();

            $this->addFlash('success', 'Adresse alternative supprimée');

            return $this->redirectToRoute('iacopo.mailing.edit', ['id' => $listId]);
        }

        return $this->redirectToRoute('iacopo.mailing.list');
    }

    /**
     * @Route("/delete/{id}", name="iacopo.mailing.delete")
     */
    public function deleteAction(int $id): Response
    {
        $list = $this->em->getRepository(MailingList::class)->find($id);
        if ($list) {
            $this->em->remove($list);
            $this->em->flush();

            $this->addFlash('success', 'Liste de diffusion supprimée');
        }

        return $this->redirectToRoute('iacopo.mailing.list');
    }
}
