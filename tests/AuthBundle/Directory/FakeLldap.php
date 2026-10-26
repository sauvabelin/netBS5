<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Directory;

use NetBS\AuthBundle\Directory\DirectoryUser;
use NetBS\AuthBundle\Directory\LldapClientException;
use NetBS\AuthBundle\Directory\LldapClientInterface;

/** In-memory stand-in for lldap. */
final class FakeLldap implements LldapClientInterface
{
    public array $users = [];
    /** @var array<int, array{id: int, displayName: string, netbsId: ?int, netbsRole: ?string, members: string[]}> */
    public array $groups = [];
    /** @var string[] group attribute names, in creation order */
    public array $attributes = [];
    public ?string $failOnCreateUser = null;
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
        $this->users[$user->uid] = ['id' => $user->uid, 'email' => $user->email, 'displayName' => $user->displayName, 'firstName' => $user->firstName, 'lastName' => $user->lastName];
    }

    public function updateUser(DirectoryUser $user): void
    {
        $this->createUser($user);
    }

    public function deleteUser(string $uid): void
    {
        unset($this->users[$uid]);
        foreach ($this->groups as &$group) {
            $group['members'] = array_values(array_diff($group['members'], [$uid]));
        }
    }

    public function createGroup(string $displayName, ?int $netbsId, ?string $netbsRole = null): int
    {
        $id = $this->nextGroupId++;
        $this->groups[$id] = ['id' => $id, 'displayName' => $displayName, 'netbsId' => $netbsId, 'netbsRole' => $netbsRole, 'members' => []];
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
        $this->groups[$groupId]['members'][] = $uid;
    }

    public function removeUserFromGroup(string $uid, int $groupId): void
    {
        if (!isset($this->users[$uid])) {
            throw new LldapClientException("unknown user $uid");
        }
        $this->groups[$groupId]['members'] = array_values(array_diff($this->groups[$groupId]['members'], [$uid]));
    }

    /** Stands in for LldapClient, whose service is tagged kernel.reset. */
    public function reset(): void
    {
    }

    private function failIfDown(): void
    {
        if ($this->down) {
            throw new LldapClientException('lldap is down');
        }
    }
}
