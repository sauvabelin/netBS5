<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Controller\Identity;

use NetBS\AuthBundle\Contract\IdentityClientPolicyInterface;
use NetBS\AuthBundle\Contract\IdentityUserResolverInterface;
use NetBS\AuthBundle\Service\ClaimsAssembler;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RefreshHookController extends AbstractController
{
    private const SECRET_HEADER = 'X-Hydra-Hook-Secret';

    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly IdentityUserResolverInterface $userResolver,
        private readonly IdentityClientPolicyInterface $policy,
        private readonly ClaimsAssembler $claims,
        private readonly string $expectedSecret,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    #[Route('/oidc-refresh-hook', name: 'oidc_refresh_hook', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        // Refuse to run unauthenticated if the operator forgot to configure
        // the shared secret. Better to break the hook loudly than to silently
        // expose claim assembly to the public internet.
        if ($this->expectedSecret === '') {
            $this->logger->error('oidc.refresh_hook: rejected - server secret not configured', [
                'remote_addr' => $request->getClientIp(),
            ]);

            return new JsonResponse(['error' => 'server_misconfigured'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $presented = (string) $request->headers->get(self::SECRET_HEADER, '');
        if ($presented === '' || !hash_equals($this->expectedSecret, $presented)) {
            $this->logger->warning('oidc.refresh_hook: rejected - bad or missing shared secret', [
                'remote_addr' => $request->getClientIp(),
                'header_present' => $presented !== '',
            ]);

            return new JsonResponse(['error' => 'unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode($request->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $subject = $payload['subject'] ?? null;
        $clientId = $payload['client_id'] ?? ($payload['client']['client_id'] ?? null);

        if (!\is_string($subject) || !\is_string($clientId)) {
            $this->logger->warning('oidc.refresh_hook: invalid payload', [
                'remote_addr' => $request->getClientIp(),
            ]);

            return new JsonResponse(['error' => 'invalid_request'], Response::HTTP_BAD_REQUEST);
        }

        $identity = $this->userResolver->resolveBySub($subject);
        if ($identity === null || $identity->isDisabled || !$this->policy->canAccess($identity, $clientId)) {
            $this->logger->info('oidc.refresh_hook: access denied', [
                'subject' => $subject,
                'client_id' => $clientId,
                'reason' => $identity === null ? 'unknown_subject' : ($identity->isDisabled ? 'disabled' : 'policy'),
            ]);

            return new JsonResponse(['error' => 'access_denied'], Response::HTTP_FORBIDDEN);
        }

        try {
            $idTokenClaims = $this->claims->assemble($identity, $clientId);
        } catch (\InvalidArgumentException $e) {
            // ClaimsAssembler throws when the client_id is unknown to it —
            // i.e. Hydra has a client the IdP doesn't recognize. Refuse the
            // refresh rather than handing Hydra a partial/empty session that
            // would invalidate the user's tokens silently.
            $this->logger->error('oidc.refresh_hook: ClaimsAssembler rejected unknown client', [
                'subject' => $subject,
                'client_id' => $clientId,
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse(['error' => 'invalid_client'], Response::HTTP_BAD_REQUEST);
        }

        $this->logger->info('oidc.refresh_hook: accepted', [
            'subject' => $subject,
            'client_id' => $clientId,
        ]);

        return new JsonResponse([
            'session' => [
                'id_token' => $idTokenClaims,
                'access_token' => [],
            ],
        ]);
    }
}
