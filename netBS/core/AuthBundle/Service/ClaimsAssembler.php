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

    /**
     * Claim names owned by the standard identity set. Policies must not return
     * these keys — we reject collisions loudly rather than letting a policy
     * silently overwrite a user's `sub`, `email`, etc.
     */
    private const RESERVED_CLAIMS = [
        'sub',
        'preferred_username',
        'email',
        'name',
        'groups',
    ];

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
            'name'               => $identity->displayName,
            'groups'             => $identity->groups,
        ];

        // No `email_verified` companion — netBS has no verification mechanism,
        // so emitting that claim would be dishonest. RPs that need verified
        // email should request a stronger out-of-band check.
        if ($identity->email !== null) {
            $standard['email'] = $identity->email;
        }

        $additional = $this->policy->additionalClaimsFor($identity, $clientId);

        // Policies opt in to claims; nulls are dropped so a policy returning
        // `'foo' => null` for "not applicable" doesn't surface as a null claim.
        $additional = array_filter(
            $additional,
            static fn ($v) => $v !== null,
        );

        // Reject collisions on reserved claims: a policy must never override
        // standard identity claims (CVE-class risk: rebinding `sub`).
        $collisions = array_intersect(array_keys($additional), self::RESERVED_CLAIMS);
        if ($collisions !== []) {
            throw new \LogicException(sprintf(
                'IdentityClientPolicy for client "%s" returned reserved claim(s): %s. '
                . 'Standard identity claims cannot be overridden by a policy.',
                $clientId,
                implode(', ', $collisions),
            ));
        }

        // Standard wins on (non-reserved) key merge as a defence-in-depth;
        // the collision check above already guarantees disjointness.
        $all = $standard + $additional;

        return array_intersect_key($all, $allowedMap);
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
