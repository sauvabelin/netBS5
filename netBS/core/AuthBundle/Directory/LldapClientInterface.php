<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Directory;

/**
 * The slice of the lldap GraphQL API the directory sync needs. Kept as an
 * interface so {@see DirectorySync} can be tested against an in-memory fake.
 *
 * Shapes returned by the list methods:
 *   users:  [['id' => string, 'email' => string, 'displayName' => string,
 *             'firstName' => string, 'lastName' => string,
 *             'attributes' => array<string, ?string> (every USER_ATTRIBUTES name, null when absent)]]
 *   groups: [['id' => int, 'displayName' => string, 'netbsId' => ?int,
 *             'netbsRole' => ?string, 'netbsComputedId' => ?int, 'members' => string[]]]
 */
interface LldapClientInterface
{
    /** Group attribute holding the netBS groupe id of a groupe group. */
    public const NETBS_ID_ATTRIBUTE = 'netbs-id';
    /** Group attribute holding the role of a role group. */
    public const NETBS_ROLE_ATTRIBUTE = 'netbs-role';
    /** Group attribute holding the id of a computed group. */
    public const NETBS_COMPUTED_ATTRIBUTE = 'netbs-computed-id';
    /**
     * User attribute holding the account's real email, absent when `mail` is
     * only lldap's mandatory placeholder. Services read this one for email.
     */
    public const NETBS_EMAIL_ATTRIBUTE = 'netbs-email';
    /** User attribute holding the account's Nextcloud quota, in bytes. */
    public const NETBS_QUOTA_ATTRIBUTE = 'netbs-quota';
    /** User attribute holding the account's main attribution, shown as its role. */
    public const NETBS_ROLE_USER_ATTRIBUTE = 'netbs-role';
    /** Custom group attributes netBS owns, with their lldap type. */
    public const GROUP_ATTRIBUTES = [
        self::NETBS_ID_ATTRIBUTE => 'INTEGER',
        self::NETBS_ROLE_ATTRIBUTE => 'STRING',
        self::NETBS_COMPUTED_ATTRIBUTE => 'INTEGER',
    ];
    /** Custom user attributes netBS owns, with their lldap type. */
    public const USER_ATTRIBUTES = [
        self::NETBS_EMAIL_ATTRIBUTE => 'STRING',
        self::NETBS_QUOTA_ATTRIBUTE => 'STRING',
        self::NETBS_ROLE_USER_ATTRIBUTE => 'STRING',
    ];

    public function listUsers(): array;

    public function listGroups(): array;

    /** @return string[] names of the custom group attributes */
    public function groupAttributes(): array;

    /** @param string $type lldap AttributeType: INTEGER, STRING, ... */
    public function addGroupAttribute(string $name, string $type): void;

    /** @return string[] names of the custom user attributes */
    public function userAttributes(): array;

    /** @param string $type lldap AttributeType: INTEGER, STRING, ... */
    public function addUserAttribute(string $name, string $type): void;

    public function createUser(DirectoryUser $user): void;

    public function updateUser(DirectoryUser $user): void;

    public function deleteUser(string $uid): void;

    /** @return int the lldap id of the new group */
    public function createGroup(string $displayName, ?int $netbsId, ?string $netbsRole = null, ?int $netbsComputedId = null): int;

    public function renameGroup(int $groupId, string $displayName): void;

    public function deleteGroup(int $groupId): void;

    public function addUserToGroup(string $uid, int $groupId): void;

    public function removeUserFromGroup(string $uid, int $groupId): void;
}
