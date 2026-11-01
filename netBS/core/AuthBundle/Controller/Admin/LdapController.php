<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\AuthBundle\Contract\DirectoryUserProviderInterface;
use NetBS\AuthBundle\Directory\DirectoryGroup;
use NetBS\AuthBundle\Directory\DirectoryNotConfiguredException;
use NetBS\AuthBundle\Directory\DirectoryProjection;
use NetBS\AuthBundle\Directory\DirectorySync;
use NetBS\AuthBundle\Directory\DirectoryUser;
use NetBS\AuthBundle\Directory\FrozenGroup;
use NetBS\AuthBundle\Directory\GroupOutline;
use NetBS\AuthBundle\Directory\LldapClientException;
use NetBS\AuthBundle\Directory\LldapClientInterface;
use NetBS\AuthBundle\Entity\DirectoryComputedGroup;
use NetBS\AuthBundle\Entity\DirectoryPublicationRule;
use NetBS\AuthBundle\ListModel\LdapGroupRow;
use NetBS\AuthBundle\ListModel\LdapMemberRow;
use NetBS\AuthBundle\Repository\DirectoryPublicationRuleRepository;
use NetBS\CoreBundle\Rule\RuleDescriber;
use NetBS\CoreBundle\Rule\RuleValidator;
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
        private readonly RuleDescriber $ruleDescriber,
        private readonly RuleValidator $ruleValidator,
        private readonly DirectoryPublicationRuleRepository $rules,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'auth.admin.ldap.index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        // Members aren't resolved here: that takes the whole snapshot. The
        // page fetches each group's count on hover, see memberCount().
        try {
            $groupRows = $this->groupRows($this->withoutPendingGroupes($this->projection->groupOutlines()));
            $notConfigured = null;
        } catch (DirectoryNotConfiguredException $e) {
            $groupRows = [];
            $notConfigured = $e->getMessage();
        }

        return $this->render('@NetBSAuth/admin/ldap/index.html.twig', [
            'notConfigured'  => $notConfigured,
            'groupRows'      => $groupRows,
            'rules'          => array_map($this->ruleView(...), $this->rules->ordered()),
            'computedGroups' => array_map($this->computedView(...), $this->em->getRepository(DirectoryComputedGroup::class)->findBy([], ['id' => 'ASC'])),
        ]);
    }

    /**
     * Drops the groupes a broken selection rule merely might pick (see FrozenGroup::$uncertain)
     * when lldap has no group for them: nothing is held back there, and such a rule reaches
     * every groupe after it. The rule itself shows as broken in the rules table. If lldap
     * can't be reached, everything is kept.
     *
     * @param list<GroupOutline> $outlines
     * @return list<GroupOutline>
     */
    private function withoutPendingGroupes(array $outlines): array
    {
        if (!array_filter($outlines, fn (GroupOutline $o) => $o->uncertain)) {
            return $outlines;
        }
        try {
            $inLldap = DirectorySync::managedGroups($this->lldap->listGroups());
        } catch (LldapClientException $e) {
            $this->logger->error('LDAP page: lldap unreachable', ['exception' => $e]);
            return $outlines;
        }

        return array_values(array_filter($outlines,
            fn (GroupOutline $o) => !$o->uncertain || isset($inLldap[DirectoryGroup::key($o->groupeId, null)])));
    }

    #[Route('/groups/{key}/count', name: 'auth.admin.ldap.group_count', requirements: ['key' => '[A-Za-z0-9_]+'], methods: ['GET'])]
    public function memberCount(string $key): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $group = $this->projection->group($key) ?? throw $this->createNotFoundException();
        return new JsonResponse($group instanceof FrozenGroup ? ['frozen' => $group->reason] : ['count' => count($group->members)]);
    }

    /** One group: its members, each compared with the lldap copy. */
    #[Route('/groups/{key}', name: 'auth.admin.ldap.group', requirements: ['key' => '[A-Za-z0-9_]+'], methods: ['GET'])]
    public function group(string $key): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $users = $this->userProvider->directoryUsers();
        $group = $this->projection->group($key, $users) ?? throw $this->createNotFoundException();
        if ($group instanceof FrozenGroup) {
            return $this->render('@NetBSAuth/admin/ldap/frozen_group.html.twig', ['group' => $group]);
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
     * A publication rule as the page shows it, with the first problem of its
     * rules (the sync freezes the groupes reaching it).
     *
     * @return array{rule: DirectoryPublicationRule, selection: string, membres: string, problem: ?string}
     */
    private function ruleView(DirectoryPublicationRule $rule): array
    {
        $membres = $rule->getSousGroupes() === DirectoryPublicationRule::SOUS_GROUPES_REGLE
            ? 'Avec les membres des sous-groupes quand ' . $this->ruleDescriber->describe($rule->getSousGroupesRuleJson(), 'attribution_sous_groupe')
            : array_flip(DirectoryPublicationRule::getSousGroupesChoices())[$rule->getSousGroupes()];
        $problems = $this->ruleValidator->validate($rule->getSelectionJson(), 'groupe', false);
        if ($rule->getSousGroupes() === DirectoryPublicationRule::SOUS_GROUPES_REGLE) {
            array_push($problems, ...$this->ruleValidator->validate($rule->getSousGroupesRuleJson(), 'attribution_sous_groupe', false));
        }

        return [
            'rule'      => $rule,
            'selection' => $this->ruleDescriber->describe($rule->getSelectionJson(), 'groupe'),
            'membres'   => $membres,
            'problem'   => $problems[0] ?? null,
        ];
    }

    /** @return array{group: DirectoryComputedGroup, membres: string, problem: ?string} */
    private function computedView(DirectoryComputedGroup $group): array
    {
        return [
            'group'   => $group,
            'membres' => $this->ruleDescriber->describe($group->getMembresJson(), 'membre'),
            'problem' => $this->ruleValidator->validate($group->getMembresJson(), 'membre', false)[0] ?? null,
        ];
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

        return array_map(fn (GroupOutline $o) => new LdapGroupRow($o->key, $o->name, $o->groupeId, $o->origin, $o->frozenReason), $outlines);
    }
}
