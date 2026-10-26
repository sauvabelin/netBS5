<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\ListModel;

/** One member on the page of an LDAP group ({@see LdapMemberListModel}). */
final class LdapMemberRow
{
    public const UP_TO_DATE = 'à jour';
    public const TO_ADD = 'à ajouter';
    public const TO_REMOVE = 'à retirer';

    public function __construct(
        public readonly string $uid,
        public readonly string $displayName,
        public readonly ?int $membreId,
        /** One of the constants above; null when lldap couldn't be read. */
        public readonly ?string $status,
    ) {
    }

    public function getId(): string
    {
        return $this->uid;
    }
}
