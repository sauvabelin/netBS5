<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\AuthBundle\Entity\DirectoryComputedGroup;
use NetBS\AuthBundle\Entity\DirectoryPublicationRule;
use NetBS\AuthBundle\Form\DirectoryComputedGroupType;
use NetBS\AuthBundle\Form\DirectoryPublicationRuleType;
use NetBS\AuthBundle\Repository\DirectoryPublicationRuleRepository;
use NetBS\CoreBundle\Utils\Modal;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Administration › Authentication › LDAP: edits the publication rules and
 * computed groups the directory projection is built from. Saving queues a
 * sync through {@see \NetBS\AuthBundle\Listener\DirectoryChangeListener}.
 */
#[Route('/ldap')]
final class LdapRulesController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DirectoryPublicationRuleRepository $rules,
    ) {
    }

    #[Route('/rules/add', name: 'auth.admin.ldap.rule.add', methods: ['GET', 'POST'])]
    public function addRule(Request $request): Response
    {
        $rule = (new DirectoryPublicationRule())->setPosition($this->rules->maxPosition() + 1);
        return $this->ruleModal($request, $rule, 'Nouvelle règle de publication', 'Règle ajoutée');
    }

    #[Route('/rules/{id}/edit', name: 'auth.admin.ldap.rule.edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editRule(Request $request, int $id): Response
    {
        return $this->ruleModal($request, $this->findRule($id), 'Modifier la règle de publication', 'Règle mise à jour');
    }

    #[Route('/rules/{id}/delete', name: 'auth.admin.ldap.rule.delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteRule(Request $request, int $id): RedirectResponse
    {
        $rule = $this->findRule($id);
        $this->checkToken($request, "ldap-rule-$id");

        $this->em->remove($rule);
        $this->em->flush();
        $this->addFlash('success', sprintf('Règle « %s » supprimée', $rule->getLabel()));
        return $this->redirectToRoute('auth.admin.ldap.index');
    }

    /** Swaps the rule with its neighbour; nothing to do at either end. */
    #[Route('/rules/{id}/move/{direction}', name: 'auth.admin.ldap.rule.move', requirements: ['id' => '\d+', 'direction' => 'up|down'], methods: ['POST'])]
    public function moveRule(Request $request, int $id, string $direction): RedirectResponse
    {
        $rule = $this->findRule($id);
        $this->checkToken($request, "ldap-rule-$id");

        $ordered = $this->rules->ordered();
        $index = array_search($rule, $ordered, true);
        $neighbour = $ordered[$direction === 'up' ? $index - 1 : $index + 1] ?? null;
        if ($neighbour !== null) {
            // Renumbered, so equal or missing positions can't make the swap a no-op.
            [$ordered[$index], $ordered[array_search($neighbour, $ordered, true)]] = [$neighbour, $rule];
            foreach ($ordered as $position => $each) {
                $each->setPosition($position + 1);
            }
            $this->em->flush();
        }
        return $this->redirectToRoute('auth.admin.ldap.index');
    }

    #[Route('/computed/add', name: 'auth.admin.ldap.computed.add', methods: ['GET', 'POST'])]
    public function addComputed(Request $request): Response
    {
        return $this->computedModal($request, new DirectoryComputedGroup(), 'Nouveau groupe calculé', 'Groupe calculé ajouté');
    }

    #[Route('/computed/{id}/edit', name: 'auth.admin.ldap.computed.edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function editComputed(Request $request, int $id): Response
    {
        return $this->computedModal($request, $this->findComputed($id), 'Modifier le groupe calculé', 'Groupe calculé mis à jour');
    }

    #[Route('/computed/{id}/delete', name: 'auth.admin.ldap.computed.delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteComputed(Request $request, int $id): RedirectResponse
    {
        $group = $this->findComputed($id);
        $this->checkToken($request, "ldap-computed-$id");

        $this->em->remove($group);
        $this->em->flush();
        $this->addFlash('success', sprintf('Groupe calculé « %s » supprimé', $group->getName()));
        return $this->redirectToRoute('auth.admin.ldap.index');
    }

    private function ruleModal(Request $request, DirectoryPublicationRule $rule, string $title, string $saved): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(DirectoryPublicationRuleType::class, $rule);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($rule);
            $this->em->flush();
            $this->addFlash('success', $saved);
            return Modal::refresh();
        }

        return $this->render('@NetBSAuth/admin/ldap/publication_rule.modal.twig', [
            'form'  => $form->createView(),
            'title' => $title,
        ], Modal::renderModal($form));
    }

    private function computedModal(Request $request, DirectoryComputedGroup $group, string $title, string $saved): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $form = $this->createForm(DirectoryComputedGroupType::class, $group);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($group);
            $this->em->flush();
            $this->addFlash('success', $saved);
            return Modal::refresh();
        }

        return $this->render('@NetBSAuth/admin/ldap/computed_group.modal.twig', [
            'form'  => $form->createView(),
            'title' => $title,
        ], Modal::renderModal($form));
    }

    private function findRule(int $id): DirectoryPublicationRule
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        return $this->rules->find($id) ?? throw $this->createNotFoundException();
    }

    private function findComputed(int $id): DirectoryComputedGroup
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        return $this->em->find(DirectoryComputedGroup::class, $id) ?? throw $this->createNotFoundException();
    }

    private function checkToken(Request $request, string $id): void
    {
        if (!$this->isCsrfTokenValid($id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }
}
