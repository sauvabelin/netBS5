<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Directory;

use NetBS\AuthBundle\Directory\DirectoryUser;
use NetBS\AuthBundle\Directory\LldapClientException;
use NetBS\AuthBundle\Directory\LldapClientInterface;

/**
 * In-memory stand-in for lldap. Like lldap, it stores user ids lowercase
 * whatever it is sent.
 */
final class FakeLldap implements LldapClientInterface
{
    public array $users = [];
    /** @var array<int, array{id: int, displayName: string, netbsId: ?int, netbsRole: ?string, netbsComputedId: ?int, members: string[]}> */
    public array $groups = [];
    /** @var string[] group attribute names, in creation order */
    public array $attributes = [];
    public ?string $failOnCreateUser = null;
    public ?string $failOnUpdateUser = null;
    /** Display name of a group whose creation fails. */
    public ?string $failOnCreateGroup = null;
    /** Display name of a group nobody can be added to. */
    public ?string $failOnAddToGroup = null;
    /** Every read fails, as when lldap is unreachable. */
    public bool $down = false;
    private int $nextGroupId = 1;

    public function membersOf(string $displayName): array
    {
        $id = $this->groupId($displayName);
        $members = $id === null ? [] : $this->groups[$id]['members'];
        sort($members);
        return $members;
    }

    public function groupId(string $displayName): ?int
    {
        foreach ($this->groups as $group) {
            if ($group['displayName'] === $displayName) {
                return $group['id'];
            }
        }
        return null;
    }

    public function listUsers(): array
    {
        $this->failIfDown();
        return array_values($this->users);
    }

    public function listGroups(): array
    {
        $this->failIfDown();
        return array_values($this->groups);
    }

    public function groupAttributes(): array
    {
        $this->failIfDown();
        return $this->attributes;
    }

    public function addGroupAttribute(string $name, string $type): void
    {
        $this->attributes[] = $name;
    }

    public function createUser(DirectoryUser $user): void
    {
        if ($user->uid === $this->failOnCreateUser) {
            throw new LldapClientException('boom');
        }
        $uid = mb_strtolower($user->uid);
        if (isset($this->users[$uid])) {
            throw new LldapClientException("user $uid already exists");
        }
        $this->users[$uid] = $this->row($uid, $user);
    }

    public function updateUser(DirectoryUser $user): void
    {
        $uid = mb_strtolower($user->uid);
        if ($user->uid === $this->failOnUpdateUser || !isset($this->users[$uid])) {
            throw new LldapClientException('boom');
        }
        $this->users[$uid] = $this->row($uid, $user);
    }

    public function deleteUser(string $uid): void
    {
        $uid = mb_strtolower($uid);
        unset($this->users[$uid]);
        foreach ($this->groups as &$group) {
            $group['members'] = array_values(array_diff($group['members'], [$uid]));
        }
    }

    public function createGroup(string $displayName, ?int $netbsId, ?string $netbsRole = null, ?int $netbsComputedId = null): int
    {
        if ($displayName === $this->failOnCreateGroup) {
            throw new LldapClientException('boom');
        }
        $id = $this->nextGroupId++;
        $this->groups[$id] = ['id' => $id, 'displayName' => $displayName, 'netbsId' => $netbsId, 'netbsRole' => $netbsRole, 'netbsComputedId' => $netbsComputedId, 'members' => []];
        return $id;
    }

    public function renameGroup(int $groupId, string $displayName): void
    {
        $this->groups[$groupId]['displayName'] = $displayName;
    }

    public function deleteGroup(int $groupId): void
    {
        unset($this->groups[$groupId]);
    }

    public function addUserToGroup(string $uid, int $groupId): void
    {
        if (($this->groups[$groupId]['displayName'] ?? null) === $this->failOnAddToGroup) {
            throw new LldapClientException('boom');
        }
        $this->groups[$groupId]['members'][] = mb_strtolower($uid);
    }

    public function removeUserFromGroup(string $uid, int $groupId): void
    {
        $uid = mb_strtolower($uid);
        if (!isset($this->users[$uid])) {
            throw new LldapClientException("unknown user $uid");
        }
        $this->groups[$groupId]['members'] = array_values(array_diff($this->groups[$groupId]['members'], [$uid]));
    }

    /** Stands in for LldapClient, whose service is tagged kernel.reset. */
    public function reset(): void
    {
    }

    private function row(string $uid, DirectoryUser $user): array
    {
        return ['id' => $uid, 'email' => $user->email, 'displayName' => $user->displayName, 'firstName' => $user->firstName, 'lastName' => $user->lastName];
    }

    private function failIfDown(): void
    {
        if ($this->down) {
            throw new LldapClientException('lldap is down');
        }
    }
}
