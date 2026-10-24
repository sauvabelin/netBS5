<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Directory;

/**
 * Makes lldap match a netBS snapshot. netBS always wins.
 *
 * Only touches what netBS owns, so lldap's built-in accounts and groups
 * (admin, lldap_admin, ...) are left alone:
 *   - users: members of the {@see MANAGED_USERS_GROUP} marker group
 *   - groups: groups carrying a {@see LldapClientInterface::NETBS_ID_ATTRIBUTE}
 *     (groupe groups) or {@see LldapClientInterface::NETBS_ROLE_ATTRIBUTE}
 *     (role groups) attribute
 * The marker group doubles as the LDAP filter services use to find netBS users.
 */
final class DirectorySync
{
    public const MANAGED_USERS_GROUP = 'netbs_users';

    /** Group attributes the sync relies on, with their lldap type. */
    private const GROUP_ATTRIBUTES = [
        LldapClientInterface::NETBS_ID_ATTRIBUTE => 'INTEGER',
        LldapClientInterface::NETBS_ROLE_ATTRIBUTE => 'STRING',
    ];

    /** @var string[] */
    private array $changes = [];
    /** @var string[] */
    private array $errors = [];
    /** @var array<string, true> uids that must not be put in groups this run (failed or unmanaged) */
    private array $skippedUids = [];

    public function __construct(
        private readonly LldapClientInterface $client,
    ) {
    }

    public function sync(DirectorySnapshot $snapshot, bool $dryRun): DirectorySyncReport
    {
        $this->changes = [];
        $this->errors = [];
        $this->skippedUids = [];

        $existingAttributes = $this->client->groupAttributes();
        foreach (self::GROUP_ATTRIBUTES as $name => $type) {
            if (!in_array($name, $existingAttributes, true)) {
                $this->apply($dryRun, sprintf('add group attribute %s', $name),
                    fn () => $this->client->addGroupAttribute($name, $type));
            }
        }

        $currentGroups = $this->client->listGroups();
        $marker = $this->findMarkerGroup($currentGroups);
        $markerId = $marker !== null
            ? $marker['id']
            : $this->apply($dryRun, sprintf('create group %s', self::MANAGED_USERS_GROUP),
                fn () => $this->client->createGroup(self::MANAGED_USERS_GROUP, null));
        $managedUids = array_fill_keys($marker['members'] ?? [], true);

        $this->syncUsers($snapshot->users, $managedUids, $markerId, $dryRun);
        $desired = [];
        foreach ([...array_values($snapshot->groups), ...array_values($snapshot->roleGroups)] as $group) {
            $desired[DirectoryGroup::key($group->netbsId, $group->role)] = $group;
        }
        $this->syncGroups($desired, $currentGroups, $dryRun);

        return new DirectorySyncReport($this->changes, $this->errors);
    }

    private function findMarkerGroup(array $currentGroups): ?array
    {
        foreach ($currentGroups as $group) {
            if ($group['displayName'] === self::MANAGED_USERS_GROUP) {
                return $group;
            }
        }
        return null;
    }

    /**
     * @param array<string, DirectoryUser> $users
     * @param array<string, true> $managedUids
     */
    private function syncUsers(array $users, array $managedUids, ?int $markerId, bool $dryRun): void
    {
        $current = [];
        foreach ($this->client->listUsers() as $row) {
            $current[mb_strtolower($row['id'])] = $row;
        }

        foreach ($users as $uid => $user) {
            if (!isset($current[$uid])) {
                $created = $this->apply($dryRun, "create user $uid", function () use ($user, $markerId) {
                    $this->client->createUser($user);
                    if ($markerId !== null) {
                        $this->client->addUserToGroup($user->uid, $markerId);
                    }
                    return true;
                });
                if (!$created && !$dryRun) {
                    $this->skippedUids[$uid] = true;
                }
                continue;
            }
            if (!isset($managedUids[$uid])) {
                $this->errors[] = "user $uid already exists in lldap but isn't managed by netBS; left untouched";
                $this->skippedUids[$uid] = true;
                continue;
            }
            if ($user->differsFrom($current[$uid])) {
                $this->apply($dryRun, "update user $uid", fn () => $this->client->updateUser($user));
            }
        }

        foreach (array_keys($managedUids) as $uid) {
            if (!isset($users[$uid])) {
                $this->apply($dryRun, "delete user $uid", fn () => $this->client->deleteUser($uid));
            }
        }
    }

    /**
     * @param array<string, DirectoryGroup> $groups keyed by {@see DirectoryGroup::key()}
     */
    private function syncGroups(array $groups, array $currentGroups, bool $dryRun): void
    {
        $managed = [];
        foreach ($currentGroups as $group) {
            if ($group['netbsId'] !== null || $group['netbsRole'] !== null) {
                $managed[DirectoryGroup::key($group['netbsId'], $group['netbsRole'])] = $group;
            }
        }

        foreach ($groups as $key => $group) {
            $current = $managed[$key] ?? null;

            if ($current === null) {
                $groupId = $this->apply($dryRun, sprintf('create group "%s"', $group->name),
                    fn () => $this->client->createGroup($group->name, $group->netbsId, $group->role));
                $currentMembers = [];
            } else {
                $groupId = $current['id'];
                $currentMembers = array_map('mb_strtolower', $current['members']);
                if ($current['displayName'] !== $group->name) {
                    $this->apply($dryRun, sprintf('rename group "%s" to "%s"', $current['displayName'], $group->name),
                        fn () => $this->client->renameGroup($groupId, $group->name));
                }
            }

            // A failed group creation leaves nothing to add members to.
            if ($groupId === null && !$dryRun) {
                continue;
            }

            foreach (array_diff($group->members, $currentMembers) as $uid) {
                if (isset($this->skippedUids[$uid])) {
                    continue;
                }
                $this->apply($dryRun, sprintf('add %s to "%s"', $uid, $group->name),
                    fn () => $this->client->addUserToGroup($uid, $groupId));
            }
            foreach (array_diff($currentMembers, $group->members) as $uid) {
                $this->apply($dryRun, sprintf('remove %s from "%s"', $uid, $group->name),
                    fn () => $this->client->removeUserFromGroup($uid, $groupId));
            }
        }

        foreach ($managed as $key => $current) {
            if (!isset($groups[$key])) {
                $this->apply($dryRun, sprintf('delete group "%s"', $current['displayName']),
                    fn () => $this->client->deleteGroup($current['id']));
            }
        }
    }

    /**
     * Records the change and runs it unless dry-running. A failing call is
     * recorded as an error and doesn't stop the rest of the sync.
     *
     * @return mixed the operation's result; null when dry-running or on failure
     */
    private function apply(bool $dryRun, string $description, callable $operation): mixed
    {
        $this->changes[] = $description;
        if ($dryRun) {
            return null;
        }
        try {
            return $operation();
        } catch (LldapClientException $e) {
            $this->errors[] = "$description: {$e->getMessage()}";
            return null;
        }
    }
}
