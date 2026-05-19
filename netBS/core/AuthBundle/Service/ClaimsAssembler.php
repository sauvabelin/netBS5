<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Service;

use NetBS\AuthBundle\Contract\IdentityClientPolicyInterface;
use NetBS\AuthBundle\Contract\IdentityDTO;

/**
 * Builds the claim set for an OIDC ID token / userinfo response.
 *
 * Hydra is the source of truth for OAuth clients. Allowed claims live in the
 * client's `metadata.allowed_claims` array; we read them on demand via the
 * admin API. A per-instance memoisation keeps repeated lookups for the same
 * client within one HTTP request to a single network call.
 */
final class ClaimsAssembler
{
    /** @var array<string, list<string>|null> client_id => allowed claims, or null when client is missing */
    private array $cache = [];

    public function __construct(
        private readonly HydraAdminClient $hydra,
        private readonly IdentityClientPolicyInterface $policy,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function assemble(IdentityDTO $identity, string $clientId): array
    {
        $allowed = $this->allowedClaimsFor($clientId);
        if ($allowed === null) {
            throw new \InvalidArgumentException("Unknown client: {$clientId}");
        }
        $allowedMap = array_flip($allowed);

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
        // Hydra rejects id_token claims whose value is null; drop them so the
        // accept-consent call doesn't fail when optional fields are unset.
        return array_filter(
            array_intersect_key($all, $allowedMap),
            static fn ($v) => $v !== null,
        );
    }

    /**
     * @return list<string>|null `null` when the client is unknown to Hydra.
     */
    private function allowedClaimsFor(string $clientId): ?array
    {
        if (array_key_exists($clientId, $this->cache)) {
            return $this->cache[$clientId];
        }

        $client = $this->hydra->getOAuthClient($clientId);
        if ($client === null) {
            return $this->cache[$clientId] = null;
        }

        $raw = $client['metadata']['allowed_claims'] ?? [];
        $claims = is_array($raw)
            ? array_values(array_map(static fn ($v) => (string) $v, $raw))
            : [];

        return $this->cache[$clientId] = $claims;
    }
}
