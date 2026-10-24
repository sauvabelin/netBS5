<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Directory;

/**
 * The slice of the lldap GraphQL API the directory sync needs. Kept as an
 * interface so {@see DirectorySync} can be tested against an in-memory fake.
 *
 * Shapes returned by the list methods:
 *   users:  [['id' => string, 'email' => string, 'displayName' => string,
 *             'firstName' => string, 'lastName' => string]]
 *   groups: [['id' => int, 'displayName' => string, 'netbsId' => ?int,
 *             'netbsRole' => ?string, 'members' => string[]]]
 */
interface LldapClientInterface
{
    public function listUsers(): array;

    public function listGroups(): array;

    public function hasGroupAttribute(string $name): bool;

    public function addIntegerGroupAttribute(string $name): void;

    public function addStringGroupAttribute(string $name): void;

    public function createUser(DirectoryUser $user): void;

    public function updateUser(DirectoryUser $user): void;

    public function deleteUser(string $uid): void;

    /** @return int the lldap id of the new group */
    public function createGroup(string $displayName, ?int $netbsId, ?string $netbsRole = null): int;

    public function renameGroup(int $groupId, string $displayName): void;

    public function deleteGroup(int $groupId): void;

    public function addUserToGroup(string $uid, int $groupId): void;

    public function removeUserFromGroup(string $uid, int $groupId): void;
}
