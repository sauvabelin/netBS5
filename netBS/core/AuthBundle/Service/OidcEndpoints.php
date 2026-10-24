<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Service;

/**
 * The public OIDC URLs a client needs: the issuer and its discovery document
 * (which lists every other endpoint).
 */
final class OidcEndpoints
{
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
}
