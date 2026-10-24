<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Controller\Admin;

use NetBS\AuthBundle\Directory\DirectoryNotConfiguredException;
use NetBS\AuthBundle\Directory\DirectoryProjection;
use NetBS\AuthBundle\Directory\DirectorySnapshot;
use NetBS\AuthBundle\Directory\DirectorySync;
use NetBS\AuthBundle\Directory\DirectoryUser;
use NetBS\AuthBundle\Directory\LldapClientException;
use NetBS\AuthBundle\ListModel\LdapChangeRow;
use NetBS\AuthBundle\ListModel\LdapGroupRow;
use NetBS\FichierBundle\Mapping\BaseGroupeType;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Administration › Authentication › LDAP: shows what the directory sync would
 * produce (same projection as `netbs:ldap:sync`, dry-run against lldap) and
 * lets an admin apply it right away. The group rules themselves are edited on
 * Structure › Types de groupes and Fonctions.
 */
#[Route('/ldap')]
final class LdapController extends AbstractController
{
    /** Errors shown as toasts after "sync now"; the rest are summarised. */
    private const MAX_ERROR_FLASHES = 5;

    public function __construct(
        private readonly DirectoryProjection $projection,
        private readonly DirectorySync $sync,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('', name: 'auth.admin.ldap.index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        try {
            $snapshot = $this->projection->snapshot();
        } catch (DirectoryNotConfiguredException $e) {
            return $this->render('@NetBSAuth/admin/ldap/index.html.twig', ['notConfigured' => $e->getMessage()]);
        }

        $report = null;
        $lldapError = null;
        try {
            $report = $this->sync->sync($snapshot, true);
        } catch (LldapClientException $e) {
            $this->logger->error('LDAP preview: lldap unreachable', ['exception' => $e]);
            $lldapError = $e->getMessage();
        }

        $changeRows = [];
        foreach ($report?->changes ?? [] as $i => $change) {
            $changeRows[] = new LdapChangeRow($i + 1, $change);
        }

        return $this->render('@NetBSAuth/admin/ldap/index.html.twig', [
            'notConfigured' => null,
            'userCount'     => count($snapshot->users),
            'groupRows'     => $this->groupRows($snapshot),
            'changeRows'    => $changeRows,
            'report'        => $report,
            'lldapError'    => $lldapError,
        ]);
    }

    /** Members of one directory group, for the "Membres" badge of the groups list. */
    #[Route('/groups/{key}/members', name: 'auth.admin.ldap.members', requirements: ['key' => '[A-Za-z0-9_]+'], methods: ['GET'])]
    public function members(string $key): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $members = $this->projection->members($key);
        if ($members === null) {
            return new JsonResponse(['error' => 'Groupe inconnu'], 404);
        }

        return new JsonResponse(array_map(
            fn (DirectoryUser $user) => ['uid' => $user->uid, 'displayName' => $user->displayName],
            $members,
        ));
    }

    /**
     * Role groups first, then groupe groups by name.
     *
     * @return LdapGroupRow[]
     */
    private function groupRows(DirectorySnapshot $snapshot): array
    {
        $rows = [];
        foreach ($snapshot->roleGroups as $role => $group) {
            $rows[] = new LdapGroupRow($role, $group->name, null, "Rôle $role", '—', count($group->members));
        }

        $sousGroupesLabels = array_flip(BaseGroupeType::getSousGroupesChoices());
        $groups = $snapshot->groups;
        uasort($groups, fn ($a, $b) => strcasecmp($a->name, $b->name));
        foreach ($groups as $netbsId => $group) {
            $rows[] = new LdapGroupRow(
                (string) $netbsId,
                $group->name,
                $netbsId,
                $group->typeName ?? '—',
                $sousGroupesLabels[$group->sousGroupes] ?? '—',
                count($group->members),
            );
        }
        return $rows;
    }

    #[Route('/sync', name: 'auth.admin.ldap.sync', methods: ['POST'])]
    public function syncNow(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('ldap_sync', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $snapshot = $this->projection->snapshot();
            $report = $this->sync->sync($snapshot, false);
        } catch (DirectoryNotConfiguredException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('auth.admin.ldap.index');
        } catch (LldapClientException $e) {
            $this->logger->error('LDAP sync now: lldap unreachable', ['exception' => $e]);
            $this->addFlash('error', 'Synchronisation impossible : lldap ne répond pas.');
            return $this->redirectToRoute('auth.admin.ldap.index');
        }

        if ($report->errors === []) {
            $this->addFlash('success', sprintf('Synchronisation terminée : %d changement(s).', count($report->changes)));
        } else {
            $this->logger->warning('LDAP sync now finished with errors', ['errors' => $report->errors]);
            $this->addFlash('warning', sprintf('Synchronisation terminée : %d changement(s), %d erreur(s).', count($report->changes), count($report->errors)));
            foreach (array_slice($report->errors, 0, self::MAX_ERROR_FLASHES) as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->redirectToRoute('auth.admin.ldap.index');
    }
}
