<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Controller\Admin;

use NetBS\AuthBundle\Contract\DirectoryUserProviderInterface;
use NetBS\AuthBundle\Directory\DirectoryGroup;
use NetBS\AuthBundle\Directory\DirectoryNotConfiguredException;
use NetBS\AuthBundle\Directory\DirectoryProjection;
use NetBS\AuthBundle\Directory\DirectoryUser;
use NetBS\AuthBundle\Directory\LldapClientException;
use NetBS\AuthBundle\Directory\LldapClientInterface;
use NetBS\AuthBundle\ListModel\LdapGroupRow;
use NetBS\AuthBundle\ListModel\LdapMemberRow;
use NetBS\FichierBundle\Mapping\BaseGroupeType;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Administration › Authentication › LDAP: the groups the directory sync
 * produces (same projection as `netbs:ldap:sync`), and per group its members
 * compared with lldap. The group rules are edited on
 * Structure › Types de groupes and Fonctions.
 */
#[Route('/ldap')]
final class LdapController extends AbstractController
{
    public function __construct(
        private readonly DirectoryProjection $projection,
        private readonly DirectoryUserProviderInterface $userProvider,
        private readonly LldapClientInterface $lldap,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('', name: 'auth.admin.ldap.index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Members aren't resolved here: that takes the whole snapshot. The
        // page fetches each group's count on hover, see memberCount().
        try {
            $outlines = $this->projection->groupOutlines();
        } catch (DirectoryNotConfiguredException $e) {
            return $this->render('@NetBSAuth/admin/ldap/index.html.twig', ['notConfigured' => $e->getMessage()]);
        }

        return $this->render('@NetBSAuth/admin/ldap/index.html.twig', [
            'notConfigured' => null,
            'groupRows'     => $this->groupRows($outlines),
        ]);
    }

    #[Route('/groups/{key}/count', name: 'auth.admin.ldap.group_count', requirements: ['key' => '[A-Za-z0-9_]+'], methods: ['GET'])]
    public function memberCount(string $key): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $group = $this->projection->group($key) ?? throw $this->createNotFoundException();
        return new JsonResponse(['count' => count($group->members)]);
    }

    /** One group: its members, each compared with the lldap copy. */
    #[Route('/groups/{key}', name: 'auth.admin.ldap.group', requirements: ['key' => '[A-Za-z0-9_]+'], methods: ['GET'])]
    public function group(string $key): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $users = $this->userProvider->directoryUsers();
        $group = $this->projection->group($key, $users) ?? throw $this->createNotFoundException();

        $lldapGroup = null;
        $lldapError = null;
        try {
            $wanted = DirectoryGroup::key($group->netbsId, $group->role);
            foreach ($this->lldap->listGroups() as $candidate) {
                if (($candidate['netbsId'] !== null || $candidate['netbsRole'] !== null)
                    && DirectoryGroup::key($candidate['netbsId'], $candidate['netbsRole']) === $wanted) {
                    $lldapGroup = $candidate;
                    break;
                }
            }
        } catch (LldapClientException $e) {
            $this->logger->error('LDAP group page: lldap unreachable', ['exception' => $e]);
            $lldapError = $e->getMessage();
        }

        return $this->render('@NetBSAuth/admin/ldap/group.html.twig', [
            'key'         => $key,
            'group'       => $group,
            'lldapGroup'  => $lldapGroup,
            'lldapError'  => $lldapError,
            'memberRows'  => $this->memberRows($group, $users, $lldapError === null ? $lldapGroup['members'] ?? [] : null),
            'sousGroupes' => array_flip(BaseGroupeType::getSousGroupesChoices())[$group->sousGroupes] ?? null,
        ]);
    }

    /**
     * Desired members, plus the lldap members to remove; pending changes first.
     *
     * @param array<string, DirectoryUser> $users
     * @param string[]|null $current lldap members, null when unknown
     * @return list<LdapMemberRow>
     */
    private function memberRows(DirectoryGroup $group, array $users, ?array $current): array
    {
        $current = $current === null ? null : array_fill_keys(array_map('mb_strtolower', $current), true);

        $rows = [];
        foreach ($group->members as $uid) {
            $user = $users[$uid];
            $status = $current === null ? null : (isset($current[$uid]) ? LdapMemberRow::UP_TO_DATE : LdapMemberRow::TO_ADD);
            $rows[] = new LdapMemberRow($uid, $user->displayName, $user->membreId, $status);
            unset($current[$uid]);
        }
        foreach (array_keys($current ?? []) as $uid) {
            $user = $users[$uid] ?? null;
            $rows[] = new LdapMemberRow($uid, $user->displayName ?? $uid, $user?->membreId, LdapMemberRow::TO_REMOVE);
        }

        usort($rows, fn (LdapMemberRow $a, LdapMemberRow $b) =>
            [$a->status === LdapMemberRow::UP_TO_DATE, mb_strtolower($a->displayName)] <=> [$b->status === LdapMemberRow::UP_TO_DATE, mb_strtolower($b->displayName)]);
        return $rows;
    }

    /**
     * Role groups first, then groupe groups by name.
     *
     * @param array<int, array{name: string, typeName: string, sousGroupes: string}> $outlines
     * @return LdapGroupRow[]
     */
    private function groupRows(array $outlines): array
    {
        $rows = [];
        foreach (DirectoryProjection::ROLE_GROUPS as $role => $name) {
            $rows[] = new LdapGroupRow($role, $name, null, "Rôle $role", '—');
        }

        $sousGroupesLabels = array_flip(BaseGroupeType::getSousGroupesChoices());
        uasort($outlines, fn ($a, $b) => strcasecmp($a['name'], $b['name']));
        foreach ($outlines as $netbsId => $outline) {
            $rows[] = new LdapGroupRow(
                (string) $netbsId,
                $outline['name'],
                $netbsId,
                $outline['typeName'],
                $sousGroupesLabels[$outline['sousGroupes']] ?? '—',
            );
        }
        return $rows;
    }
}
