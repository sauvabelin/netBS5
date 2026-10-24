<?php

declare(strict_types=1);

namespace App\Tests\Ldap;

use App\Ldap\DirectoryUser;
use App\Ldap\LldapClientException;
use App\Ldap\LldapClientInterface;

/** In-memory stand-in for lldap. */
final class FakeLldap implements LldapClientInterface
{
    public array $users = [];
    /** @var array<int, array{id: int, displayName: string, netbsId: ?int, members: string[]}> */
    public array $groups = [];
    public bool $hasAttribute = false;
    public ?string $failOnCreateUser = null;
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
        return array_values($this->users);
    }

    public function listGroups(): array
    {
        return array_values($this->groups);
    }

    public function hasGroupAttribute(string $name): bool
    {
        return $this->hasAttribute;
    }

    public function addIntegerGroupAttribute(string $name): void
    {
        $this->hasAttribute = true;
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

    public function createGroup(string $displayName, ?int $netbsId): int
    {
        $id = $this->nextGroupId++;
        $this->groups[$id] = ['id' => $id, 'displayName' => $displayName, 'netbsId' => $netbsId, 'members' => []];
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
        $this->groups[$groupId]['members'] = array_values(array_diff($this->groups[$groupId]['members'], [$uid]));
    }
}
