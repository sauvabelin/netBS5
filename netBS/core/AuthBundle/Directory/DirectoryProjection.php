<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Directory;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\AuthBundle\Contract\DirectoryUserProviderInterface;
use NetBS\AuthBundle\Entity\DirectoryComputedGroup;
use NetBS\AuthBundle\Entity\DirectoryPublicationRule;
use NetBS\AuthBundle\Repository\DirectoryPublicationRuleRepository;
use NetBS\AuthBundle\Rule\DirectoryRuleLabeler;
use NetBS\CoreBundle\Rule\RuleDescriber;
use NetBS\CoreBundle\Rule\RuleEvaluationException;
use NetBS\CoreBundle\Rule\RuleEvaluator;
use NetBS\CoreBundle\Rule\RuleValidator;
use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\FichierBundle\Service\FichierConfig;
use Psr\Log\LoggerInterface;

/**
 * Computes what the LDAP directory should contain, from netBS data. The
 * rules are edited on Administration › Authentication › LDAP.
 *
 *   - users: whatever the app's {@see DirectoryUserProviderInterface} lists
 *   - groups: the groupes a {@see DirectoryPublicationRule} selects, each
 *     with the first rule (by position) that selects it; members follow
 *     that rule's "sous-groupes" mode, see {@see GroupeMemberResolver}
 *   - computed groups: one per {@see DirectoryComputedGroup}, holding the
 *     membres its rule matches
 *   - role groups: one per {@see ROLE_GROUPS} entry, always present, holding
 *     the directory users that hold the role ({@see DirectoryUser::$roles})
 * Membres without a directory user are dropped. A group whose rule is broken
 * is frozen ({@see FrozenGroup}) instead of being computed as empty.
 */
final class DirectoryProjection
{
    /** Roles published as directory groups (role => lldap group name). */
    public const ROLE_GROUPS = [
        'ROLE_NEXTCLOUD_ADMIN' => 'nextcloud-admins',
        'ROLE_WIKI_ADMIN' => 'wiki-admins',
    ];

    /**
     * Names netBS can't give a group: the marker group of managed users and
     * lldap's built-in groups. Lowercase.
     */
    public const RESERVED_NAMES = [DirectorySync::MANAGED_USERS_GROUP, 'lldap_admin', 'lldap_password_manager', 'lldap_strict_readonly'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly FichierConfig $fichierConfig,
        private readonly DirectoryUserProviderInterface $userProvider,
        private readonly GroupeMemberResolver $resolver,
        private readonly DirectoryPublicationRuleRepository $rules,
        private readonly RuleValidator $validator,
        private readonly RuleEvaluator $evaluator,
        private readonly RuleDescriber $describer,
        private readonly DirectoryRuleLabeler $labeler,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @throws DirectoryNotConfiguredException when there is no rule, or the
     *         rules give no group and freeze none
     */
    public function snapshot(): DirectorySnapshot
    {
        [$rules, $computed] = $this->configuration();
        $checker = $this->checker();
        $users = $this->userProvider->directoryUsers();
        $uidByMembre = self::uidByMembre($users);

        $selection = $this->select($rules, $checker);
        if ($selection !== []) {
            $this->fetchAttributions(null);
        }
        $names = self::names($selection, $computed);

        $groups = [];
        $frozen = [];
        foreach ($selection as $id => [$groupe, $rule, $problem]) {
            $group = $problem === null ? $this->buildGroup($groupe, $names[$id], $rule, $uidByMembre, $checker) : null;
            if ($group instanceof DirectoryGroup) {
                $groups[$id] = $group;
            } else {
                $frozen["id:$id"] = new FrozenGroup("id:$id", $names[$id], $problem ?? $group);
            }
        }

        $computedGroups = [];
        $membres = $computed !== [] ? $this->membres(array_keys($uidByMembre)) : [];
        foreach ($computed as $id => $entity) {
            $group = $this->buildComputed($entity, $membres, $uidByMembre, $checker);
            if ($group instanceof DirectoryGroup) {
                $computedGroups[$id] = $group;
            } else {
                $frozen[$group->key] = $group;
            }
        }

        if ($groups === [] && $computedGroups === [] && $frozen === []) {
            throw new DirectoryNotConfiguredException();
        }
        // One line per reason: a broken selection rule freezes every groupe reaching it.
        $keysByReason = [];
        foreach ($frozen as $group) {
            $keysByReason[$group->reason][] = $group->key;
        }
        foreach ($keysByReason as $reason => $keys) {
            $this->logger->warning('directory.group_frozen', ['reason' => $reason, 'count' => count($keys), 'keys' => $keys]);
        }

        return new DirectorySnapshot($users, $groups, $this->roleGroups($users), $computedGroups, $frozen);
    }

    /**
     * Group names end up in DNs. Nextcloud escapes these characters in DNs
     * (`\28` for `(`, ...) and lldap can't find entries by such DNs, so they
     * are replaced.
     */
    public static function dnSafe(string $name): string
    {
        return trim(strtr($name, [
            '(' => '[', ')' => ']',
            ',' => ' ', '=' => '-', '+' => '-', '<' => ' ', '>' => ' ', ';' => ' ',
            '"' => "'", '#' => ' ', '*' => ' ', '\\' => ' ',
        ]));
    }

    /**
     * Every group's name and origin, without members: cheap, for listing.
     * Groupe groups and computed groups are frozen here only when a rule is
     * invalid; one that fails while resolving members shows as frozen on its
     * own page.
     *
     * @return list<GroupOutline> role groups, computed groups by id, groupe groups by id
     * @throws DirectoryNotConfiguredException when there is no rule
     */
    public function groupOutlines(): array
    {
        [$rules, $computed] = $this->configuration();
        $checker = $this->checker();
        $selection = $this->select($rules, $checker);
        $names = self::names($selection, $computed);

        $outlines = [];
        foreach (self::ROLE_GROUPS as $role => $name) {
            $outlines[] = new GroupOutline($role, $name, null, "Rôle $role");
        }
        foreach ($computed as $id => $entity) {
            $problem = $checker->problem($entity->getMembres());
            $outlines[] = new GroupOutline("c$id", self::dnSafe($entity->getName()), null, $this->computedOrigin($entity),
                $problem === null ? null : $checker->reason($entity->getMembres(), $problem));
        }
        foreach ($selection as $id => [, $rule, $problem]) {
            $outlines[] = new GroupOutline((string) $id, $names[$id], $id, $rule === null ? '—' : $this->origin($rule, $checker), $problem);
        }
        return $outlines;
    }

    /**
     * One group as {@see snapshot()} computes it, without resolving the others.
     *
     * @param string $key netBS groupe id, "c" + computed group id, or a role of {@see ROLE_GROUPS}
     * @param array<string, DirectoryUser>|null $users the directory users, when
     *        the caller has them; otherwise loaded, as little as the group needs
     * @return DirectoryGroup|FrozenGroup|null null when $key matches no group
     */
    public function group(string $key, ?array $users = null): DirectoryGroup|FrozenGroup|null
    {
        if (preg_match('/^c(\d+)$/', $key, $match)) {
            $entity = $this->em->find(DirectoryComputedGroup::class, (int) $match[1]);
            if ($entity === null) {
                return null;
            }
            $uidByMembre = $users !== null ? self::uidByMembre($users) : $this->userProvider->uidsByMembre();
            return $this->buildComputed($entity, $this->membres(array_keys($uidByMembre)), $uidByMembre, $this->checker());
        }
        if (!ctype_digit($key)) {
            return isset(self::ROLE_GROUPS[$key]) ? $this->roleGroups($users ?? $this->userProvider->directoryUsers())[$key] : null;
        }

        [$rules, $computed] = $this->configuration(false);
        $checker = $this->checker();
        $selection = $this->select($rules, $checker);
        if (!isset($selection[(int) $key])) {
            return null;
        }
        [$groupe, $rule, $problem] = $selection[(int) $key];
        $name = self::names($selection, $computed)[$groupe->getId()];
        if ($problem !== null) {
            return new FrozenGroup("id:$key", $name, $problem);
        }

        $this->fetchAttributions([$groupe->getId(), ...array_map(fn (BaseGroupe $g) => $g->getId(), $this->resolver->descendants($groupe))]);
        $uidByMembre = $users !== null ? self::uidByMembre($users) : $this->userProvider->uidsByMembre();
        $group = $this->buildGroup($groupe, $name, $rule, $uidByMembre, $checker);
        return $group instanceof DirectoryGroup ? $group : new FrozenGroup("id:$key", $name, $group);
    }

    /**
     * @return array{list<DirectoryPublicationRule>, array<int, DirectoryComputedGroup>}
     * @throws DirectoryNotConfiguredException when there is neither (and $required)
     */
    private function configuration(bool $required = true): array
    {
        $rules = $this->rules->ordered();
        $computed = [];
        foreach ($this->em->getRepository(DirectoryComputedGroup::class)->findBy([], ['id' => 'ASC']) as $entity) {
            $computed[$entity->getId()] = $entity;
        }
        if ($required && $rules === [] && $computed === []) {
            throw new DirectoryNotConfiguredException();
        }
        return [$rules, $computed];
    }

    private function checker(): DirectoryRuleChecker
    {
        return new DirectoryRuleChecker($this->validator, $this->evaluator, $this->labeler);
    }

    /**
     * Which rule publishes each groupe: the first whose selection matches.
     * A groupe that reaches a broken selection rule can't tell whether that
     * rule wins, so it freezes; so does one whose winning rule has a broken
     * sous-groupes rule.
     *
     * @param list<DirectoryPublicationRule> $rules
     * @return array<int, array{BaseGroupe, ?DirectoryPublicationRule, ?string}> groupe id =>
     *         [groupe, winning rule, freeze reason], selected or frozen groupes only, by id
     */
    private function select(array $rules, DirectoryRuleChecker $checker): array
    {
        if ($rules === []) {
            return [];
        }

        $selection = [];
        foreach ($this->allGroupes() as $id => $groupe) {
            foreach ($rules as $rule) {
                $problem = $checker->problem($rule->getSelection());
                if ($problem !== null) {
                    $selection[$id] = [$groupe, $rule, $checker->reason($rule->getSelection(), $problem)];
                    continue 2;
                }
                try {
                    if (!$checker->matches($rule->getSelection(), $groupe)) {
                        continue;
                    }
                } catch (RuleEvaluationException $e) {
                    $selection[$id] = [$groupe, $rule, $checker->reason($rule->getSelection(), $e->getMessage())];
                    continue 2;
                }

                $sousRule = $rule->getSousGroupesRule();
                $problem = $rule->getSousGroupes() === DirectoryPublicationRule::SOUS_GROUPES_REGLE && $sousRule !== null
                    ? $checker->problem($sousRule) : null;
                $selection[$id] = [$groupe, $rule, $problem === null ? null : $checker->reason($sousRule, $problem)];
                continue 2;
            }
        }
        return $selection;
    }

    /**
     * Every groupe with its type, catégorie and enfants, by id. Parents are
     * groupes too, so selection rules walking up find them loaded.
     *
     * @return array<int, BaseGroupe>
     */
    private function allGroupes(): array
    {
        return $this->em->createQueryBuilder()
            ->select('g', 't', 'c', 'e')
            ->from($this->fichierConfig->getGroupeClass(), 'g', 'g.id')
            ->leftJoin('g.groupeType', 't')
            ->leftJoin('t.groupeCategorie', 'c')
            ->leftJoin('g.enfants', 'e')
            ->orderBy('g.id')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param array<int, string> $uidByMembre
     * @return DirectoryGroup|string the group, or why it is frozen
     */
    private function buildGroup(BaseGroupe $groupe, string $name, DirectoryPublicationRule $rule, array $uidByMembre, DirectoryRuleChecker $checker): DirectoryGroup|string
    {
        try {
            $membreIds = $this->resolver->membreIds($groupe, $rule->getSousGroupes(), $rule->getSousGroupesRuleJson());
        } catch (RuleEvaluationException $e) {
            return $checker->reason($rule->getSousGroupesRule(), $e->getMessage());
        }

        return new DirectoryGroup($groupe->getId(), $name, self::uids($membreIds, $uidByMembre), origin: $this->origin($rule, $checker));
    }

    /**
     * @param array<int, object> $membres by id
     * @param array<int, string> $uidByMembre
     */
    private function buildComputed(DirectoryComputedGroup $entity, array $membres, array $uidByMembre, DirectoryRuleChecker $checker): DirectoryGroup|FrozenGroup
    {
        $key = DirectoryGroup::key(null, null, $entity->getId());
        $name = self::dnSafe($entity->getName());
        $rule = $entity->getMembres();

        $problem = $checker->problem($rule);
        if ($problem !== null) {
            return new FrozenGroup($key, $name, $checker->reason($rule, $problem));
        }

        $membreIds = [];
        try {
            foreach ($membres as $id => $membre) {
                if ($checker->matches($rule, $membre)) {
                    $membreIds[] = $id;
                }
            }
        } catch (RuleEvaluationException $e) {
            return new FrozenGroup($key, $name, $checker->reason($rule, $e->getMessage()));
        }

        return new DirectoryGroup(null, $name, self::uids($membreIds, $uidByMembre), computedId: $entity->getId(), origin: $this->computedOrigin($entity));
    }

    /**
     * @param int[] $membreIds
     * @param array<int, string> $uidByMembre
     * @return string[] uids, unique, membres without an account dropped
     */
    private static function uids(array $membreIds, array $uidByMembre): array
    {
        $uids = [];
        foreach ($membreIds as $membreId) {
            $uid = $uidByMembre[$membreId] ?? null;
            if ($uid !== null) {
                $uids[$uid] = $uid;
            }
        }
        return array_values($uids);
    }

    /** Memoized per rule: describing a rule reads the entities it names. */
    private function origin(DirectoryPublicationRule $rule, DirectoryRuleChecker $checker): string
    {
        return $checker->memo('origin:' . $rule->getId(), fn () => $this->describeOrigin($rule));
    }

    private function describeOrigin(DirectoryPublicationRule $rule): string
    {
        $mode = $rule->getSousGroupes() === DirectoryPublicationRule::SOUS_GROUPES_REGLE
            ? 'avec les membres des sous-groupes quand ' . $this->describer->describe($rule->getSousGroupesRuleJson(), 'attribution_sous_groupe')
            : mb_strtolower(array_flip(DirectoryPublicationRule::getSousGroupesChoices())[$rule->getSousGroupes()]);

        return sprintf('« %s » — %s', $rule->getLabel(), $mode);
    }

    private function computedOrigin(DirectoryComputedGroup $entity): string
    {
        return 'Groupe calculé — ' . $this->describer->describe($entity->getMembresJson(), 'membre');
    }

    /**
     * @param array<string, DirectoryUser> $users
     * @return array<string, DirectoryGroup>
     */
    private function roleGroups(array $users): array
    {
        $groups = [];
        foreach (self::ROLE_GROUPS as $role => $name) {
            $members = array_values(array_map(fn (DirectoryUser $user) => $user->uid, self::roleHolders($users, $role)));
            sort($members);
            $groups[$role] = new DirectoryGroup(null, $name, $members, $role, origin: "Rôle $role");
        }
        return $groups;
    }

    /**
     * @param array<string, DirectoryUser> $users
     * @return array<int, string> membre id => uid
     */
    private static function uidByMembre(array $users): array
    {
        $uids = [];
        foreach ($users as $uid => $user) {
            if ($user->membreId !== null) {
                $uids[$user->membreId] = $uid;
            }
        }
        return $uids;
    }

    /**
     * @param array<string, DirectoryUser> $users
     * @return array<string, DirectoryUser> the users holding $role, keys kept
     */
    private static function roleHolders(array $users, string $role): array
    {
        return array_filter($users, fn (DirectoryUser $user) => in_array($role, $user->roles, true));
    }

    /**
     * lldap group names are unique and two netBS groupes can share a nom;
     * reserved, role and computed group names come first. A clash gets the
     * groupe id as suffix. Published groupes are named before frozen ones, so
     * a broken rule can't rename a healthy group.
     *
     * @param array<int, array{BaseGroupe, mixed, mixed}> $selection by groupe id, ordered
     * @param array<int, DirectoryComputedGroup> $computed
     * @return array<int, string> groupe id => name
     */
    private static function names(array $selection, array $computed): array
    {
        $seen = array_fill_keys([...self::RESERVED_NAMES, ...array_map('mb_strtolower', self::ROLE_GROUPS)], true);
        foreach ($computed as $entity) {
            $seen[mb_strtolower(self::dnSafe($entity->getName()))] = true;
        }

        $names = [];
        $published = array_filter($selection, fn (array $entry) => $entry[2] === null);
        foreach ($published + $selection as $id => [$groupe]) {
            $name = self::dnSafe(trim($groupe->getNom()));
            if (isset($seen[mb_strtolower($name)])) {
                $name = sprintf('%s [%d]', $name, $id);
            }
            $seen[mb_strtolower($name)] = true;
            $names[$id] = $name;
        }
        return $names;
    }

    /**
     * The membres with an account, by id, with their attributions, groupes
     * and fonctions loaded for the computed groups' rules.
     *
     * @param int[] $ids
     * @return array<int, object>
     */
    private function membres(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        return $this->em->createQueryBuilder()
            ->select('m', 'a', 'ag', 'af')
            ->from($this->fichierConfig->getMembreClass(), 'm', 'm.id')
            ->leftJoin('m.attributions', 'a')
            ->leftJoin('a.groupe', 'ag')
            ->leftJoin('a.fonction', 'af')
            ->where('m.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->orderBy('m.id')
            ->getQuery()
            ->getResult();
    }

    // Fetch joins initialize the collections of groupes already managed.

    /** @param int[]|null $groupeIds null for every groupe */
    private function fetchAttributions(?array $groupeIds): void
    {
        $qb = $this->em->createQueryBuilder()
            ->select('g', 'a', 'am')
            ->from($this->fichierConfig->getGroupeClass(), 'g')
            ->leftJoin('g.attributions', 'a')
            ->leftJoin('a.membre', 'am');
        if ($groupeIds !== null) {
            $qb->where('g.id IN (:ids)')->setParameter('ids', $groupeIds);
        }
        $qb->getQuery()->getResult();
    }
}
