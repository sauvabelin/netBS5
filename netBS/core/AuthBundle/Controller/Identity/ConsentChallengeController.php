<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Controller\Identity;

use NetBS\AuthBundle\Contract\IdentityClientPolicyInterface;
use NetBS\AuthBundle\Contract\IdentityUserResolverInterface;
use NetBS\AuthBundle\Service\ClaimsAssembler;
use NetBS\AuthBundle\Service\HydraAdminClient;
use NetBS\AuthBundle\Service\HydraClientException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ConsentChallengeController extends AbstractController
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly HydraAdminClient $hydra,
        private readonly IdentityUserResolverInterface $userResolver,
        private readonly IdentityClientPolicyInterface $policy,
        private readonly ClaimsAssembler $claims,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    #[Route('/oidc-consent', name: 'oidc_consent', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $consentChallenge = $request->query->get('consent_challenge');
        if (!\is_string($consentChallenge) || $consentChallenge === '') {
            $this->logger->warning('oidc.consent: rejected - missing challenge', [
                'decision' => 'reject',
                'reason'   => 'missing_challenge',
            ]);

            throw new \InvalidArgumentException('consent_challenge missing');
        }

        $consentRequest = $this->hydra->getConsentRequest($consentChallenge);

        // Defensive shape validation. Hydra's API contract guarantees these
        // fields, but a future Hydra version or a misconfigured deployment
        // could surprise us — fail closed instead of fatal-ing with an
        // "undefined index" inside the claims pipeline.
        $subject = $consentRequest['subject'] ?? null;
        $clientId = $consentRequest['client']['client_id'] ?? null;
        if (!\is_string($subject) || $subject === '' || !\is_string($clientId) || $clientId === '') {
            $this->logger->error('oidc.consent: invalid Hydra response shape', [
                'consent_challenge' => $consentChallenge,
                'has_subject' => isset($consentRequest['subject']),
                'has_client_id' => isset($consentRequest['client']['client_id']),
            ]);

            return $this->rejectAndRedirect($consentChallenge, 'invalid_request', 'Invalid consent request from authorization server.', [
                'subject'   => $consentRequest['subject'] ?? null,
                'client_id' => $consentRequest['client']['client_id'] ?? null,
                'reason'    => 'invalid_hydra_payload',
            ]);
        }

        $requestedScopes = $consentRequest['requested_scope'] ?? [];

        $identity = $this->userResolver->resolveBySub($subject);
        if ($identity === null || $identity->isDisabled) {
            return $this->rejectAndRedirect($consentChallenge, 'access_denied', 'User not found or disabled', [
                'subject'   => $subject,
                'client_id' => $clientId,
                'reason'    => $identity === null ? 'unknown_subject' : 'disabled',
            ]);
        }

        // Identity-validity gate. The IdP only checks that the user exists
        // and is not disabled; per-RP authorisation (which users may sign in
        // to which client) lives in the RP itself — e.g. Nextcloud user_oidc's
        // "required group" setting, the Wiki OIDC plugin's allow-list, etc.
        // Every decision is logged.
        if (!$this->policy->canAccess($identity, $clientId)) {
            return $this->rejectAndRedirect($consentChallenge, 'access_denied', "User does not have access to {$clientId}", [
                'subject'   => $subject,
                'client_id' => $clientId,
                'reason'    => 'policy_denied',
            ]);
        }

        try {
            $idTokenClaims = $this->claims->assemble($identity, $clientId);
        } catch (\InvalidArgumentException $e) {
            // ClaimsAssembler throws when the client_id is unknown to it
            // (Hydra has a client we don't, e.g. drift between clients.yaml
            // and Hydra state). Reject the consent cleanly rather than
            // 500-ing the user-facing browser redirect.
            $this->logger->error('oidc.consent: ClaimsAssembler rejected unknown client', [
                'client_id' => $clientId,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);

            return $this->rejectAndRedirect($consentChallenge, 'invalid_client', 'Client is not registered with the identity provider.', [
                'subject'   => $subject,
                'client_id' => $clientId,
                'reason'    => 'unknown_client',
            ]);
        }

        $accept = $this->hydra->acceptConsentRequest($consentChallenge, [
            'grant_scope' => $requestedScopes,
            'grant_access_token_audience' => $consentRequest['requested_access_token_audience'] ?? [],
            'remember' => true,
            'remember_for' => 3600 * 12,
            // Hydra's session schema defines id_token/access_token as JSON
            // objects (maps). Empty PHP arrays JSON-encode as `[]` (a JSON
            // array) which Hydra rejects with "error is unrecognizable" —
            // cast to object so they serialize as `{}` when empty.
            'session' => [
                'id_token' => empty($idTokenClaims) ? (object) [] : $idTokenClaims,
                'access_token' => (object) [],
            ],
        ]);

        $this->logger->info('oidc.consent: accepted', [
            'subject'           => $subject,
            'client_id'         => $clientId,
            'consent_challenge' => $consentChallenge,
            'decision'          => 'accept',
            'reason'            => 'policy_allowed',
            'granted_scopes'    => $requestedScopes,
        ]);

        return new RedirectResponse($accept['redirect_to']);
    }

    /**
     * @param array<string, mixed> $logContext extra structured fields for the warning log
     */
    private function rejectAndRedirect(string $consentChallenge, string $error, string $description, array $logContext = []): RedirectResponse
    {
        $this->logger->warning('oidc.consent: rejected', $logContext + [
            'consent_challenge' => $consentChallenge,
            'decision'          => 'reject',
            'error'             => $error,
            'error_description' => $description,
        ]);

        try {
            $reject = $this->hydra->rejectConsentRequest($consentChallenge, [
                'error' => $error,
                'error_description' => $description,
            ]);

            return new RedirectResponse($reject['redirect_to']);
        } catch (HydraClientException $e) {
            // If even the reject call fails, we have no redirect target; bail
            // out with a plain 502 so the operator notices in logs.
            $this->logger->critical('oidc.consent: reject call failed', [
                'consent_challenge' => $consentChallenge,
                'error' => $e->getMessage(),
            ]);

            return new RedirectResponse('/');
        }
    }
}
