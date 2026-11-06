<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\AuthBundle\Contract\DirectoryUserProviderInterface;
use NetBS\AuthBundle\Directory\DirectoryGroup;
use NetBS\AuthBundle\Directory\DirectoryNotConfiguredException;
use NetBS\AuthBundle\Directory\DirectoryProjection;
use NetBS\AuthBundle\Directory\DirectoryUser;
use NetBS\AuthBundle\Directory\GroupOutline;
use NetBS\AuthBundle\Directory\LldapClientException;
use NetBS\AuthBundle\Directory\LldapClientInterface;
use NetBS\AuthBundle\Entity\DirectoryComputedGroup;
use NetBS\AuthBundle\Entity\DirectoryPublicationRule;
use NetBS\AuthBundle\ListModel\LdapGroupRow;
use NetBS\AuthBundle\ListModel\LdapMemberRow;
use NetBS\AuthBundle\Repository\DirectoryPublicationRuleRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Administration › Authentication › LDAP: the publication rules and computed
 * groups (edited through {@see LdapRulesController}), the groups the
 * directory sync produces from them (same projection as `netbs:ldap:sync`),
 * and per group its members compared with lldap.
 */
#[Route('/ldap')]
final class LdapController extends AbstractController
{
    public function __construct(
        private readonly DirectoryProjection $projection,
        private readonly DirectoryUserProviderInterface $userProvider,
        private readonly LldapClientInterface $lldap,
        private readonly LoggerInterface $logger,
        private readonly DirectoryPublicationRuleRepository $rules,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'auth.admin.ldap.index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $rules = array_map(fn (DirectoryPublicationRule $rule) => [
            'rule'      => $rule,
            'selection' => $this->projection->describeSelection($rule),
            'membres'   => $this->projection->describeMembres($rule),
            'problem'   => $this->projection->ruleProblem($rule),
        ], $this->rules->ordered());
        $computedGroups = array_map(fn (DirectoryComputedGroup $group) => [
            'group'   => $group,
            'membres' => $this->projection->describeComputed($group),
            'problem' => $this->projection->computedProblem($group),
        ], $this->em->getRepository(DirectoryComputedGroup::class)->findBy([], ['id' => 'ASC']));

        // All or nothing: while a rule is broken, the sync is suspended and no group is listed.
        // Members aren't resolved here: that takes the whole snapshot. The
        // page fetches each group's count on hover, see memberCount().
        $groupRows = [];
        $notConfigured = null;
        if (array_filter([...$rules, ...$computedGroups], fn (array $view) => $view['problem'] !== null)) {
            $notConfigured = 'Une règle est en erreur (voir ci-dessus) : la synchronisation est suspendue jusqu\'à sa correction.';
        } else {
            try {
                $groupRows = $this->groupRows($this->projection->groupOutlines());
            } catch (DirectoryNotConfiguredException $e) {
                $notConfigured = $e->getMessage();
            }
        }

        return $this->render('@NetBSAuth/admin/ldap/index.html.twig', [
            'notConfigured'  => $notConfigured,
            'groupRows'      => $groupRows,
            'rules'          => $rules,
            'computedGroups' => $computedGroups,
        ]);
    }

    #[Route('/groups/{key}/count', name: 'auth.admin.ldap.group_count', requirements: ['key' => '[A-Za-z0-9_]+'], methods: ['GET'])]
    public function memberCount(string $key): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        try {
            $group = $this->projection->group($key) ?? throw $this->createNotFoundException();
        } catch (DirectoryNotConfiguredException $e) {
            return new JsonResponse(['error' => $e->getMessage()], Response::HTTP_CONFLICT);
        }
        return new JsonResponse(['count' => count($group->members)]);
    }

    /** One group: its members, each compared with the lldap copy. */
    #[Route('/groups/{key}', name: 'auth.admin.ldap.group', requirements: ['key' => '[A-Za-z0-9_]+'], methods: ['GET'])]
    public function group(string $key): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $users = $this->userProvider->directoryUsers();
        try {
            $group = $this->projection->group($key, $users) ?? throw $this->createNotFoundException();
        } catch (DirectoryNotConfiguredException $e) {
            $this->addFlash('danger', $e->getMessage());
            return $this->redirectToRoute('auth.admin.ldap.index');
        }

        $lldapGroup = null;
        $lldapError = null;
        try {
            $wanted = DirectoryGroup::key($group->netbsId, $group->role, $group->computedId);
            foreach ($this->lldap->listGroups() as $candidate) {
                if (($candidate['netbsId'] !== null || $candidate['netbsRole'] !== null || $candidate['netbsComputedId'] !== null)
                    && DirectoryGroup::key($candidate['netbsId'], $candidate['netbsRole'], $candidate['netbsComputedId']) === $wanted) {
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
     * Role groups first, then computed groups, then groupe groups by name.
     *
     * @param list<GroupOutline> $outlines
     * @return LdapGroupRow[]
     */
    private function groupRows(array $outlines): array
    {
        $rank = fn (GroupOutline $o) => match (true) {
            isset(DirectoryProjection::ROLE_GROUPS[$o->key]) => 0,
            $o->groupeId === null                           => 1,
            default                                         => 2,
        };
        usort($outlines, fn (GroupOutline $a, GroupOutline $b) => [$rank($a), mb_strtolower($a->name)] <=> [$rank($b), mb_strtolower($b->name)]);

        return array_map(fn (GroupOutline $o) => new LdapGroupRow($o->key, $o->name, $o->groupeId, $o->origin), $outlines);
    }
}
