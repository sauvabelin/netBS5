<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Service;

/**
 * Resolves the public OIDC endpoints exposed by Hydra and the curated
 * catalogue of claims a client can opt into.
 */
final class OidcEndpoints
{
    private const CLAIM_CATALOGUE = [
        // Universal identity claims, emitted for every client (subject to
        // per-client `metadata.allowed_claims` opt-in via the admin form).
        'sub',
        'preferred_username',
        'email',
        'name',
        'groups',
        // Optional per-user RP-related claims. The policy emits these
        // uniformly for every consent; the per-client allow-list decides
        // which ones actually reach the RP.
        'nextcloud_account',
        'nextcloud_admin',
        'nextcloud_quota',
        'wiki_account',
        'wiki_admin',
    ];

    public function __construct(private readonly string $issuer)
    {
    }

    public function issuer(): string
    {
        return rtrim($this->issuer, '/');
    }

    public function discovery(): string
    {
        return $this->issuer() . '/.well-known/openid-configuration';
    }

    public function authorization(): string { return $this->issuer() . '/oauth2/auth'; }
    public function token(): string         { return $this->issuer() . '/oauth2/token'; }
    public function userinfo(): string      { return $this->issuer() . '/userinfo'; }
    public function jwks(): string          { return $this->issuer() . '/.well-known/jwks.json'; }
    public function logout(): string        { return $this->issuer() . '/oauth2/sessions/logout'; }

    /** @return string[] */
    public function claimCatalogue(): array
    {
        return self::CLAIM_CATALOGUE;
    }
}
