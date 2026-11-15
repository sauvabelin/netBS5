<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Directory;

final class DirectoryUser
{
    public function __construct(
        /** lldap user id, the same string as the OIDC subject ({@see \NetBS\AuthBundle\Contract\Subject}). */
        public readonly string $uid,
        /** lldap `mail`: required and unique, so a placeholder when the account has no real address. */
        public readonly string $email,
        public readonly string $displayName,
        public readonly string $firstName,
        public readonly string $lastName,
        /** netBS membre linked to the account, if any; not stored in lldap. */
        public readonly ?int $membreId = null,
        /** @var string[] keys of {@see DirectoryProjection::ROLE_GROUPS} the user holds; not stored in lldap. */
        public readonly array $roles = [],
        /** Kept in lldap but in no group, e.g. while the account has a temporary password. */
        public readonly bool $groupsSuspended = false,
        /** The real address only, null when $email is a placeholder; stored as the netbs-email attribute. */
        public readonly ?string $contactEmail = null,
        /** Nextcloud quota in bytes; stored as the netbs-quota attribute. */
        public readonly ?int $quota = null,
        /** Main active attribution ("Fonction - Groupe"), null without one; stored as the netbs-role attribute. */
        public readonly ?string $role = null,
    ) {
    }

    /**
     * The custom lldap attributes netBS owns on this user, null when absent.
     *
     * @return array<string, ?string> keyed by {@see LldapClientInterface::USER_ATTRIBUTES}
     */
    public function attributes(): array
    {
        return [
            LldapClientInterface::NETBS_EMAIL_ATTRIBUTE => $this->contactEmail,
            LldapClientInterface::NETBS_QUOTA_ATTRIBUTE => $this->quota === null ? null : (string) $this->quota,
            LldapClientInterface::NETBS_ROLE_USER_ATTRIBUTE => $this->role,
        ];
    }

    /** Whether the lldap copy ($current, a row from {@see LldapClientInterface::listUsers()}) needs updating. */
    public function differsFrom(array $current): bool
    {
        return $this->email !== $current['email']
            || $this->attributes() !== $current['attributes']
            || $this->displayName !== $current['displayName']
            || $this->firstName !== $current['firstName']
            || $this->lastName !== $current['lastName'];
    }
}
