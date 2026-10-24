<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Service;

use NetBS\AuthBundle\Contract\IdentityDTO;

/**
 * Builds the claim set for an OIDC ID token / userinfo response.
 *
 * OIDC is kept to login only: the standard identity claims, each released by
 * its scope (OIDC Core 5.4). `sub` is always released. Groups, admin rights
 * and quota reach the apps through the LDAP directory, never through tokens.
 */
final class ClaimsAssembler
{
    private const SCOPE_OF_CLAIM = [
        'preferred_username' => 'profile',
        'name'               => 'profile',
        'email'              => 'email',
    ];

    /**
     * @param list<string>|null $grantedScopes scopes granted to this token;
     *                                         null skips scope filtering
     * @return array<string, mixed>
     */
    public function assemble(IdentityDTO $identity, ?array $grantedScopes): array
    {
        $claims = [
            'sub'                => $identity->sub,
            'preferred_username' => $identity->preferredUsername,
            'name'               => $identity->displayName,
        ];

        // No `email_verified` companion — netBS has no verification mechanism,
        // so emitting that claim would be dishonest.
        if ($identity->email !== null) {
            $claims['email'] = $identity->email;
        }

        if ($grantedScopes === null) {
            return $claims;
        }

        return array_filter(
            $claims,
            static fn (string $claim) => !isset(self::SCOPE_OF_CLAIM[$claim])
                || \in_array(self::SCOPE_OF_CLAIM[$claim], $grantedScopes, true),
            \ARRAY_FILTER_USE_KEY,
        );
    }
}
