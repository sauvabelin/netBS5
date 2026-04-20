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
use Symfony\Component\Form\FormInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Psr\Log\LoggerInterface;

class DefaultController extends AbstractController
{
    private $em;
    private $targetListModel;
    private $aliasListModel;
    private $targetResolver;
    private $logger;

    public function __construct(
        EntityManagerInterface $em,
        MailingTargetListModel $targetListModel,
        MailingAliasListModel $aliasListModel,
        MailingTargetResolver $targetResolver,
        LoggerInterface $logger
    ) {
        $this->em = $em;
        $this->targetListModel = $targetListModel;
        $this->aliasListModel = $aliasListModel;
        $this->targetResolver = $targetResolver;
        $this->logger = $logger;
    }

    /**
     * Helper method to collect all form errors and add them as flash messages
     */
    private function addFormErrorsAsFlash(FormInterface $form): void
    {
        // Get global form errors
        foreach ($form->getErrors() as $error) {
            $this->addFlash('error', $error->getMessage());
        }

        // Get field-specific errors
        foreach ($form->all() as $child) {
            if (!$child->isValid()) {
                foreach ($child->getErrors() as $error) {
                    $fieldName = $child->getConfig()->getOption('label') ?: $child->getName();
                    $this->addFlash('error', $fieldName . ': ' . $error->getMessage());
                }
            }
        }
    }

    #[Route('/', name: 'iacopo.mailing.list')]
    public function indexAction(): Response
    {
        return $this->render('@IacopoMailing/default/index.html.twig');
    }

    #[Route('/create', name: 'iacopo.mailing.create')]
    public function createAction(Request $request): Response
    {
        $mailingList = new MailingList();
        $this->denyAccessUnlessGranted('create', $mailingList);

        $form = $this->createForm(MailingListType::class, $mailingList);

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    $this->em->persist($mailingList);
                    $this->em->flush();

                    $this->addFlash('success', 'Liste de diffusion créée avec succès');

                    // Return 201 to trigger page reload
                    return new Response('', 201);
                } catch (UniqueConstraintViolationException $e) {
                    $this->addFlash('error', 'Cette adresse de base est déjà utilisée.');
                } catch (\Exception $e) {
                    $this->logger->error('Failed to create mailing list', [
                        'exception' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $this->addFlash('error', 'Une erreur est survenue lors de la création de la liste.');
                }
            } else {
                // Add validation errors as flash messages
                $this->addFormErrorsAsFlash($form);
            }
        }

        return $this->render('@IacopoMailing/default/create.modal.twig', [
            'form' => $form->createView()
        ]);
    }

    #[Route('/edit/{id}', name: 'iacopo.mailing.edit')]
    public function editAction(int $id, Request $request): Response
    {
        $mailingList = $this->em->getRepository(MailingList::class)->find($id);

        if (!$mailingList) {
            throw $this->createNotFoundException('Liste non trouvée');
        }

        $this->denyAccessUnlessGranted('update', $mailingList);

        // Form for editing the mailing list itself
        $listForm = $this->createForm(MailingListType::class, $mailingList);
        $listForm->handleRequest($request);

        if ($listForm->isSubmitted()) {
            if ($listForm->isValid()) {
                try {
                    $mailingList->setUpdatedAt(new \DateTime());
                    $this->em->flush();

                    $this->addFlash('success', 'Liste mise à jour avec succès');

                    return $this->redirectToRoute('iacopo.mailing.edit', ['id' => $id]);
                } catch (UniqueConstraintViolationException $e) {
                    $this->addFlash('error', 'Cette adresse de base est déjà utilisée.');
                } catch (\Exception $e) {
                    $this->logger->error('Failed to update mailing list', [
                        'list_id' => $id,
                        'exception' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $this->addFlash('error', 'Une erreur est survenue lors de la mise à jour.');
                }
            } else {
                $this->addFormErrorsAsFlash($listForm);
            }
        }

        // Get the last used type from query parameter
        $lastType = $request->query->get('lastType', MailingTarget::TYPE_EMAIL);

        // Form for adding new target
        $newTarget = new MailingTarget();
        $newTarget->setMailingList($mailingList);
        $newTarget->setType($lastType);
        $targetForm = $this->createForm(MailingTargetType::class, $newTarget);

        $targetForm->handleRequest($request);

        if ($targetForm->isSubmitted()) {
            if ($targetForm->isValid()) {
                try {
                    $this->em->persist($newTarget);
                    $this->em->flush();

                    $this->addFlash('success', 'Destinataire ajouté avec succès');

                    return $this->redirectToRoute('iacopo.mailing.edit', [
                        'id' => $id,
                        'lastType' => $newTarget->getType()
                    ]);
                } catch (\Exception $e) {
                    $this->logger->error('Failed to add mailing target', [
                        'list_id' => $id,
                        'exception' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $this->addFlash('error', 'Une erreur est survenue lors de l\'ajout du destinataire.');
                }
            } else {
                $this->addFormErrorsAsFlash($targetForm);
            }
        }

        // Form for adding new alias
        $newAlias = new MailingListAlias();
        $newAlias->setMailingList($mailingList);
        $aliasForm = $this->createForm(MailingListAliasType::class, $newAlias);

        $aliasForm->handleRequest($request);

        if ($aliasForm->isSubmitted()) {
            if ($aliasForm->isValid()) {
                try {
                    $this->em->persist($newAlias);
                    $this->em->flush();

                    $this->addFlash('success', 'Adresse alternative ajoutée avec succès');

                    return $this->redirectToRoute('iacopo.mailing.edit', ['id' => $id]);
                } catch (\Exception $e) {
                    $this->logger->error('Failed to add mailing alias', [
                        'list_id' => $id,
                        'exception' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $this->addFlash('error', 'Une erreur est survenue lors de l\'ajout de l\'adresse alternative.');
                }
            } else {
                $this->addFormErrorsAsFlash($aliasForm);
            }
        }

        // Configure list models
        $this->targetListModel->setMailingListId($id);
        $this->aliasListModel->setMailingListId($id);

        // Calculate recipient count
        $recipientCount = $this->targetResolver->countMailingList($mailingList);

        // Turbo requires 422 on POST when the form has errors (otherwise it rejects
        // a 200 response as "must redirect"). A successful submit already returned
        // via redirectToRoute() above, so if we're here with a submitted-but-invalid
        // form, emit 422.
        $hasInvalidSubmission =
            ($listForm->isSubmitted() && !$listForm->isValid())
            || ($targetForm->isSubmitted() && !$targetForm->isValid())
            || ($aliasForm->isSubmitted() && !$aliasForm->isValid());

        $status = $hasInvalidSubmission
            ? Response::HTTP_UNPROCESSABLE_ENTITY
            : Response::HTTP_OK;

        return $this->render('@IacopoMailing/default/edit.html.twig', [
            'mailingList' => $mailingList,
            'listForm' => $listForm->createView(),
            'targetForm' => $targetForm->createView(),
            'aliasForm' => $aliasForm->createView(),
            'lastType' => $lastType,
            'recipientCount' => $recipientCount,
        ], new Response('', $status));
    }

    #[Route('/target/{id}/edit', name: 'iacopo.mailing.target.edit')]
    public function editTargetAction(int $id, Request $request): Response
    {
        $target = $this->em->getRepository(MailingTarget::class)->find($id);

        if (!$target) {
            throw $this->createNotFoundException('Destinataire non trouvé');
        }

        $this->denyAccessUnlessGranted('update', $target);

        $form = $this->createForm(MailingTargetType::class, $target);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    $this->em->flush();

                    $this->addFlash('success', 'Destinataire modifié avec succès');

                    return $this->redirectToRoute('iacopo.mailing.edit', [
                        'id' => $target->getMailingList()->getId(),
                        'lastType' => $target->getType()
                    ]);
                } catch (\Exception $e) {
                    $this->logger->error('Failed to edit mailing target', [
                        'target_id' => $id,
                        'exception' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    $this->addFlash('error', 'Une erreur est survenue lors de la modification du destinataire.');
                }
            } else {
                $this->addFormErrorsAsFlash($form);
            }
        }

        $status = $form->isSubmitted() && !$form->isValid()
            ? Response::HTTP_UNPROCESSABLE_ENTITY
            : Response::HTTP_OK;

        return $this->render('@IacopoMailing/default/edit_target.modal.twig', [
            'form' => $form->createView(),
            'target' => $target,
        ], new Response('', $status));
    }

    #[Route('/{id}/toggle-active', name: 'iacopo.mailing.toggle_active', methods: ['POST'])]
    public function toggleActiveAction(int $id): JsonResponse
    {
        $mailingList = $this->em->getRepository(MailingList::class)->find($id);

        if (!$mailingList) {
            return new JsonResponse(['error' => 'Liste non trouvée'], 404);
        }

        $this->denyAccessUnlessGranted('update', $mailingList);

        $mailingList->setActive(!$mailingList->isActive());
        $this->em->flush();

        return new JsonResponse([
            'success' => true,
            'active' => $mailingList->isActive()
        ]);
    }

    #[Route('/target/{id}/delete', name: 'iacopo.mailing.target.delete', methods: ['POST'])]
    public function deleteTargetAction(int $id): Response
    {
        $target = $this->em->getRepository(MailingTarget::class)->find($id);

        if ($target) {
            $this->denyAccessUnlessGranted('delete', $target);

            $listId = $target->getMailingList()->getId();
            $this->em->remove($target);
            $this->em->flush();

            $this->addFlash('success', 'Destinataire supprimé');

            return $this->redirectToRoute('iacopo.mailing.edit', ['id' => $listId]);
        }

        return $this->redirectToRoute('iacopo.mailing.list');
    }

    #[Route('/alias/{id}/delete', name: 'iacopo.mailing.alias.delete', methods: ['POST'])]
    public function deleteAliasAction(int $id): Response
    {
        $alias = $this->em->getRepository(MailingListAlias::class)->find($id);

        if ($alias) {
            $this->denyAccessUnlessGranted('delete', $alias);

            $listId = $alias->getMailingList()->getId();
            $this->em->remove($alias);
            $this->em->flush();

            $this->addFlash('success', 'Adresse alternative supprimée');

            return $this->redirectToRoute('iacopo.mailing.edit', ['id' => $listId]);
        }

        return $this->redirectToRoute('iacopo.mailing.list');
    }

    #[Route('/{id}/recipients', name: 'iacopo.mailing.recipients', methods: ['GET'])]
    public function getRecipientsAction(int $id): JsonResponse
    {
        $mailingList = $this->em->getRepository(MailingList::class)->find($id);

        if (!$mailingList) {
            return new JsonResponse(['error' => 'Liste non trouvée'], 404);
        }

        $this->denyAccessUnlessGranted('read', $mailingList);

        $emails = $this->targetResolver->resolveMailingList($mailingList);

        return new JsonResponse([
            'count' => count($emails),
            'emails' => $emails
        ]);
    }

    #[Route('/delete/{id}', name: 'iacopo.mailing.delete', methods: ['POST'])]
    public function deleteAction(int $id): Response
    {
        $list = $this->em->getRepository(MailingList::class)->find($id);
        if ($list) {
            $this->denyAccessUnlessGranted('delete', $list);

            $this->em->remove($list);
            $this->em->flush();

            $this->addFlash('success', 'Liste de diffusion supprimée');
        }

        return $this->redirectToRoute('iacopo.mailing.list');
    }
}
