<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Directory;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\AuthBundle\Contract\DirectoryUserProviderInterface;
use NetBS\AuthBundle\Entity\DirectoryComputedGroup;
use NetBS\AuthBundle\Entity\DirectoryPublicationRule;
use NetBS\AuthBundle\Repository\DirectoryPublicationRuleRepository;
use NetBS\CoreBundle\Entity\StoredRule;
use NetBS\CoreBundle\Rule\RuleDescriber;
use NetBS\CoreBundle\Rule\RuleEvaluationException;
use NetBS\CoreBundle\Rule\RuleEvaluator;
use NetBS\CoreBundle\Rule\RuleValidator;
use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\FichierBundle\Service\FichierConfig;

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
 * Membres without a directory user are dropped. All or nothing: a broken rule
 * (invalid, deleted reference, failing at run time) stops the whole sync with a
 * {@see DirectoryNotConfiguredException} naming it, rather than publishing groups
 * computed without it.
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
    ) {
    }

    /**
     * @throws DirectoryNotConfiguredException when there is no rule, the rules
     *         give no group, or a rule is broken
     */
    public function snapshot(): DirectorySnapshot
    {
        [$rules, $computed] = $this->configuration();
        $this->assertUsable($rules, $computed);
        $users = $this->userProvider->directoryUsers();
        $uidByMembre = self::uidByMembre($users);

        $selection = $this->select($rules);
        if ($selection !== []) {
            $this->fetchAttributions(null);
        }
        $names = self::names($selection, $computed);

        $groups = [];
        foreach ($selection as $id => [$groupe, $rule]) {
            $groups[$id] = $this->buildGroup($groupe, $names[$id], $rule, $uidByMembre);
        }

        $computedGroups = [];
        $membres = $computed !== [] ? $this->membres(array_keys($uidByMembre)) : [];
        foreach ($computed as $id => $entity) {
            $computedGroups[$id] = $this->buildComputed($entity, $membres, $uidByMembre);
        }

        if ($groups === [] && $computedGroups === []) {
            throw new DirectoryNotConfiguredException();
        }

        return new DirectorySnapshot($users, $groups, $this->roleGroups($users), $computedGroups);
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
     * The first problem of a publication rule's rules, or null. The LDAP page
     * shows it on the rule; any problem suspends the sync.
     */
    public function ruleProblem(DirectoryPublicationRule $rule): ?string
    {
        return $this->problem($rule->getSelection())
            ?? ($rule->getSousGroupes() === DirectoryPublicationRule::SOUS_GROUPES_REGLE ? $this->problem($rule->getSousGroupesRule()) : null);
    }

    public function computedProblem(DirectoryComputedGroup $entity): ?string
    {
        return $this->problem($entity->getMembres());
    }

    public function describeSelection(DirectoryPublicationRule $rule): string
    {
        return $this->describer->describe($rule->getSelectionJson(), 'groupe');
    }

    /** « Avec les membres de tous ses sous-groupes », ... */
    public function describeMembres(DirectoryPublicationRule $rule): string
    {
        return $rule->getSousGroupes() === DirectoryPublicationRule::SOUS_GROUPES_REGLE
            ? 'Avec les membres des sous-groupes quand ' . $this->describer->describe($rule->getSousGroupesRuleJson(), 'attribution_sous_groupe')
            : array_flip(DirectoryPublicationRule::getSousGroupesChoices())[$rule->getSousGroupes()];
    }

    public function describeComputed(DirectoryComputedGroup $entity): string
    {
        return $this->describer->describe($entity->getMembresJson(), 'membre');
    }

    /**
     * Every group's name and origin, without members: cheap, for listing.
     * Doesn't validate the rules: the LDAP page checks them first (see
     * {@see ruleProblem()}) and lists no group while one is broken.
     *
     * @return list<GroupOutline> role groups, computed groups by id, groupe groups by id
     * @throws DirectoryNotConfiguredException when there is no rule, or a selection rule fails
     */
    public function groupOutlines(): array
    {
        [$rules, $computed] = $this->configuration();
        $selection = $this->select($rules);
        $names = self::names($selection, $computed);

        $outlines = [];
        foreach (self::ROLE_GROUPS as $role => $name) {
            $outlines[] = new GroupOutline($role, $name, null, "Rôle $role");
        }
        foreach ($computed as $id => $entity) {
            $outlines[] = new GroupOutline("c$id", self::dnSafe($entity->getName()), null, $this->computedOrigin($entity));
        }
        $origins = [];
        foreach ($selection as $id => [, $rule]) {
            $origin = $origins[$rule->getId()] ??= $this->origin($rule);
            $outlines[] = new GroupOutline((string) $id, $names[$id], $id, $origin);
        }
        return $outlines;
    }

    /**
     * One group as {@see snapshot()} computes it, without resolving the others.
     *
     * @param string $key netBS groupe id, "c" + computed group id, or a role of {@see ROLE_GROUPS}
     * @param array<string, DirectoryUser>|null $users the directory users, when
     *        the caller has them; otherwise loaded, as little as the group needs
     * @return DirectoryGroup|null null when $key matches no group
     * @throws DirectoryNotConfiguredException when a rule is broken
     */
    public function group(string $key, ?array $users = null): ?DirectoryGroup
    {
        if (!ctype_digit($key) && !preg_match('/^c\d+$/', $key)) {
            return isset(self::ROLE_GROUPS[$key]) ? $this->roleGroups($users ?? $this->userProvider->directoryUsers())[$key] : null;
        }

        [$rules, $computed] = $this->configuration(false);
        $this->assertUsable($rules, $computed);

        if ($key[0] === 'c') {
            $entity = $computed[(int) substr($key, 1)] ?? null;
            if ($entity === null) {
                return null;
            }
            $uidByMembre = $users !== null ? self::uidByMembre($users) : $this->userProvider->uidsByMembre();
            return $this->buildComputed($entity, $this->membres(array_keys($uidByMembre)), $uidByMembre);
        }

        $selection = $this->select($rules);
        if (!isset($selection[(int) $key])) {
            return null;
        }
        [$groupe, $rule] = $selection[(int) $key];

        $this->fetchAttributions([$groupe->getId(), ...array_map(fn (BaseGroupe $g) => $g->getId(), $this->resolver->descendants($groupe))]);
        $uidByMembre = $users !== null ? self::uidByMembre($users) : $this->userProvider->uidsByMembre();
        return $this->buildGroup($groupe, self::names($selection, $computed)[$groupe->getId()], $rule, $uidByMembre);
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

    /**
     * @param list<DirectoryPublicationRule> $rules
     * @param array<int, DirectoryComputedGroup> $computed
     * @throws DirectoryNotConfiguredException naming the first broken rule
     */
    private function assertUsable(array $rules, array $computed): void
    {
        foreach ($rules as $rule) {
            if (($problem = $this->ruleProblem($rule)) !== null) {
                throw DirectoryNotConfiguredException::brokenRule(self::ruleOwner($rule), $problem);
            }
        }
        foreach ($computed as $entity) {
            if (($problem = $this->computedProblem($entity)) !== null) {
                throw DirectoryNotConfiguredException::brokenRule(self::computedOwner($entity), $problem);
            }
        }
    }

    private function problem(?StoredRule $rule): ?string
    {
        return $rule === null ? null : ($this->validator->validate($rule->getDefinition(), $rule->getContext(), false)[0] ?? null);
    }

    private static function ruleOwner(DirectoryPublicationRule $rule): string
    {
        return sprintf('Règle LDAP « %s »', $rule->getLabel());
    }

    private static function computedOwner(DirectoryComputedGroup $entity): string
    {
        return sprintf('Groupe calculé « %s »', $entity->getName());
    }

    /**
     * Which rule publishes each groupe: the first whose selection matches.
     *
     * @param list<DirectoryPublicationRule> $rules
     * @return array<int, array{BaseGroupe, DirectoryPublicationRule}> groupe id => [groupe, winning rule], selected groupes only, by id
     * @throws DirectoryNotConfiguredException when a selection rule fails
     */
    private function select(array $rules): array
    {
        if ($rules === []) {
            return [];
        }

        $selection = [];
        foreach ($this->allGroupes() as $id => $groupe) {
            foreach ($rules as $rule) {
                try {
                    if ($this->evaluator->matchesStrict($rule->getSelection()->rule(), 'groupe', $groupe)) {
                        $selection[$id] = [$groupe, $rule];
                        continue 2;
                    }
                } catch (RuleEvaluationException $e) {
                    throw DirectoryNotConfiguredException::brokenRule(self::ruleOwner($rule), $e->getMessage());
                }
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

    /** @param array<int, string> $uidByMembre */
    private function buildGroup(BaseGroupe $groupe, string $name, DirectoryPublicationRule $rule, array $uidByMembre): DirectoryGroup
    {
        try {
            $membreIds = $this->resolver->membreIds($groupe, $rule->getSousGroupes(), $rule->getSousGroupesRule()?->rule());
        } catch (RuleEvaluationException $e) {
            throw DirectoryNotConfiguredException::brokenRule(self::ruleOwner($rule), $e->getMessage());
        }

        return new DirectoryGroup($groupe->getId(), $name, self::uids($membreIds, $uidByMembre), origin: $this->origin($rule));
    }

    /**
     * @param array<int, object> $membres by id
     * @param array<int, string> $uidByMembre
     */
    private function buildComputed(DirectoryComputedGroup $entity, array $membres, array $uidByMembre): DirectoryGroup
    {
        $rule = $entity->getMembres()->rule();
        $membreIds = [];
        try {
            foreach ($membres as $id => $membre) {
                if ($this->evaluator->matchesStrict($rule, 'membre', $membre)) {
                    $membreIds[] = $id;
                }
            }
        } catch (RuleEvaluationException $e) {
            throw DirectoryNotConfiguredException::brokenRule(self::computedOwner($entity), $e->getMessage());
        }

        return new DirectoryGroup(null, self::dnSafe($entity->getName()), self::uids($membreIds, $uidByMembre),
            computedId: $entity->getId(), origin: $this->computedOrigin($entity));
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

    private function origin(DirectoryPublicationRule $rule): string
    {
        $membres = $this->describeMembres($rule);

        return sprintf('« %s » — %s', $rule->getLabel(), mb_strtolower(mb_substr($membres, 0, 1)) . mb_substr($membres, 1));
    }

    private function computedOrigin(DirectoryComputedGroup $entity): string
    {
        return 'Groupe calculé — ' . $this->describeComputed($entity);
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
     * groupe id as suffix.
     *
     * @param array<int, array{BaseGroupe, mixed}> $selection by groupe id, ordered
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
        foreach ($selection as $id => [$groupe]) {
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
