<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Service;

use App\Entity\BSUser;
use NetBS\AuthBundle\Contract\IdentityClientPolicyInterface;
use NetBS\AuthBundle\Contract\IdentityDTO;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class IdentityClientPolicy implements IdentityClientPolicyInterface
{
    private readonly LoggerInterface $logger;

    /**
     * Per-request memo of BSUser lookups by `sub`. canAccess and
     * additionalClaimsFor are typically called back-to-back on the same
     * identity (consent flow and refresh hook both do this), so a tiny
     * in-memory map collapses 2+ Doctrine fetches into one. The map is
     * cleared between requests because the service is request-scoped in
     * the web SAPI; in worker contexts the map size is bounded by the
     * number of distinct subjects handled per process.
     *
     * @var array<string, BSUser|false> false = "looked up, not found"
     */
    private array $userCache = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    private function loadUser(string $sub): ?BSUser
    {
        if (array_key_exists($sub, $this->userCache)) {
            $cached = $this->userCache[$sub];
            return $cached === false ? null : $cached;
        }

        $user = $this->em->getRepository(BSUser::class)->findOneBy(['username' => $sub]);
        $this->userCache[$sub] = $user instanceof BSUser ? $user : false;

        return $user instanceof BSUser ? $user : null;
    }

    /**
     * Whether `$identity` may obtain tokens for `$clientId`.
     *
     * IdP gates only on identity validity. Per-RP access policy lives in
     * the RP (e.g. Nextcloud user_oidc 'required group' setting, Wiki OIDC
     * plugin's allow-list).
     *
     * Decision flow:
     *  1. `isDisabled` => deny.
     *  2. Local `BSUser` not found for `sub` => deny.
     *  3. Otherwise => allow.
     *
     * Every decision is logged at INFO so post-hoc audits are possible.
     */
    public function canAccess(IdentityDTO $identity, string $clientId): bool
    {
        $decision = $this->decide($identity, $clientId, $reason);

        $this->logger->info('oidc.client_access', [
            'sub'       => $identity->sub,
            'client_id' => $clientId,
            'decision'  => $decision ? 'allow' : 'deny',
            'reason'    => $reason,
        ]);

        return $decision;
    }

    private function decide(IdentityDTO $identity, string $clientId, ?string &$reason): bool
    {
        if ($identity->isDisabled) {
            $reason = 'identity_disabled';
            return false;
        }

        if ($this->loadUser($identity->sub) === null) {
            $reason = 'user_not_found';
            return false;
        }

        $reason = 'allow';
        return true;
    }

    /**
     * Hook for emitting additional per-client claims beyond the universal
     * identity ones. The IdP itself emits no RP-specific claims — RPs derive
     * any authorisation signals they need from `groups` (or from their own
     * local config). Kept for interface compatibility and as an extension
     * point for future configurable claim sources.
     */
    public function additionalClaimsFor(IdentityDTO $identity, string $clientId): array
    {
        return [];
    }
}
