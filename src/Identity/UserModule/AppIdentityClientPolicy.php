<?php

declare(strict_types=1);

namespace App\Identity\UserModule;

use App\Entity\BSUser;
use NetBS\AuthBundle\Contract\IdentityClientPolicyInterface;
use NetBS\AuthBundle\Contract\IdentityDTO;
use NetBS\AuthBundle\Dto\OidcClientDto;
use NetBS\AuthBundle\Service\HydraAdminClient;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

final class AppIdentityClientPolicy implements IdentityClientPolicyInterface
{
    /**
     * Per-request memo of BSUser lookups by `sub`, so repeated access checks
     * for the same identity cost one Doctrine fetch. The map is
     * cleared between requests because the service is request-scoped in
     * the web SAPI; in worker contexts the map size is bounded by the
     * number of distinct subjects handled per process.
     *
     * @var array<string, BSUser|false> false = "looked up, not found"
     */
    private array $userCache = [];

    /** @var array<string, string|false|null> client_id => required flag (null = none, false = unknown client) */
    private array $requiredFlagCache = [];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly HydraAdminClient $hydra,
        private readonly LoggerInterface $logger,
    ) {
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
     * Finer per-RP access policy (groups, roles) still lives in the RP (e.g.
     * Nextcloud user_oidc 'required group' setting, Wiki OIDC plugin's
     * allow-list); the IdP only enforces the client's account flag.
     *
     * Decision flow:
     *  1. `isDisabled` => deny.
     *  2. Local `BSUser` not found for `sub` => deny.
     *  3. Client has `metadata.required_flag` (e.g. nextcloud_account) and the
     *     user's flag is off => deny. Unknown client or flag => deny.
     *  4. Otherwise => allow.
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

        $requiredFlag = $this->requiredFlagFor($clientId);
        if ($requiredFlag === false) {
            $reason = 'unknown_client_or_flag';
            return false;
        }
        $hasFlag = match ($requiredFlag) {
            null => true,
            'nextcloud_account' => (bool) $user->hasNextcloudAccount(),
            'wiki_account' => (bool) $user->hasWikiAccount(),
        };
        if (!$hasFlag) {
            $reason = 'missing_flag_' . $requiredFlag;
            return false;
        }

        $reason = 'allow';
        return true;
    }

    /**
     * @return string|false|null the client's required flag, null when it has
     *                           none, false when the client or flag is unknown
     */
    private function requiredFlagFor(string $clientId): string|false|null
    {
        if (array_key_exists($clientId, $this->requiredFlagCache)) {
            return $this->requiredFlagCache[$clientId];
        }

        $client = $this->hydra->getOAuthClient($clientId);
        $flag = $client['metadata']['required_flag'] ?? null;
        if ($client === null || ($flag !== null && !\in_array($flag, OidcClientDto::REQUIRED_FLAGS, true))) {
            return $this->requiredFlagCache[$clientId] = false;
        }

        return $this->requiredFlagCache[$clientId] = $flag;
    }
}
