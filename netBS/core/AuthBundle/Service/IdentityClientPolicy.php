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
    /**
     * Per-client gating map: `clientId` => `BSUser` method whose boolean return
     * value gates access. Clients not listed here fall through to default-allow
     * (see {@see canAccess}). When adding a new internal client that should be
     * toggleable per-user, add its `client_id` here and expose the corresponding
     * boolean field on `BSUser` (and in the admin user list, see
     * `BSUserList::PROPERTY`).
     *
     * @var array<string, string>
     */
    private const CLIENT_ACCESS_FIELDS = [
        'nextcloud' => 'hasNextcloudAccount',
        'wiki'      => 'hasWikiAccount',
    ];

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
     * Decision flow:
     *  1. Resolve the local `BSUser` by `sub`. No user / disabled => deny.
     *  2. If the client appears in {@see CLIENT_ACCESS_FIELDS}, gate on the
     *     corresponding boolean field of `BSUser` (admins toggle these per
     *     user via the user list UI).
     *  3. Otherwise default-allow. This is intentional for a small internal
     *     scout-association deployment where every netBS user is trusted on
     *     every internally-operated OIDC client. If you ever federate to a
     *     less-trusted client, add it to `CLIENT_ACCESS_FIELDS` (or replace
     *     this policy with one that reads `metadata.access_groups` from the
     *     Hydra client).
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

        $user = $this->loadUser($identity->sub);
        if ($user === null) {
            $reason = 'user_not_found';
            return false;
        }

        if (isset(self::CLIENT_ACCESS_FIELDS[$clientId])) {
            $method = self::CLIENT_ACCESS_FIELDS[$clientId];
            $allowed = (bool) $user->{$method}();
            $reason = $allowed ? 'client_gate_allow' : 'client_gate_deny';
            return $allowed;
        }

        $reason = 'default_allow_unlisted_client';
        return true;
    }

    public function additionalClaimsFor(IdentityDTO $identity, string $clientId): array
    {
        $user = $this->loadUser($identity->sub);
        if ($user === null) {
            return [];
        }

        return match ($clientId) {
            'nextcloud' => [
                'nextcloud_admin' => $user->isNextcloudAdmin(),
                'nextcloud_quota' => $user->getNextcloudQuota(),
            ],
            'wiki' => [
                'wiki_admin' => $user->isWikiAdmin(),
            ],
            default => [],
        };
    }
}
