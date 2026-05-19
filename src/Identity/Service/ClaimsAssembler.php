<?php

declare(strict_types=1);

namespace App\Identity\Service;

use App\Identity\Contract\IdentityClientPolicyInterface;
use App\Identity\Contract\IdentityDTO;

final class ClaimsAssembler
{
    /**
     * @param array<string, array{allowed_claims: string[]}> $clients
     */
    public function __construct(
        private readonly array $clients,
        private readonly IdentityClientPolicyInterface $policy,
    ) {
    }

    public static function fromConfigPath(string $path, IdentityClientPolicyInterface $policy): self
    {
        return new self(ClientConfigLoader::load($path), $policy);
    }

    /**
     * @return array<string, mixed>
     */
    public function assemble(IdentityDTO $identity, string $clientId): array
    {
        if (!isset($this->clients[$clientId])) {
            throw new \InvalidArgumentException("Unknown client: {$clientId}");
        }
        $allowed = array_flip($this->clients[$clientId]['allowed_claims']);

        $standard = [
            'sub'                => $identity->sub,
            'preferred_username' => $identity->preferredUsername,
            'email'              => $identity->email,
            'email_verified'     => $identity->emailVerified,
            'name'               => $identity->displayName,
            'updated_at'         => $identity->updatedAt->getTimestamp(),
            'groups'             => $identity->groups,
        ];

        $additional = $this->policy->additionalClaimsFor($identity, $clientId);

        $all = $additional + $standard;
        return array_intersect_key($all, $allowed);
    }
}
