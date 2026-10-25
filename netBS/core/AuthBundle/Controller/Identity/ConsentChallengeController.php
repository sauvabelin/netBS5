<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Controller\Identity;

use NetBS\AuthBundle\Contract\IdentityClientPolicyInterface;
use NetBS\AuthBundle\Contract\IdentityUserResolverInterface;
use NetBS\AuthBundle\Service\ClaimsAssembler;
use NetBS\AuthBundle\Service\HydraAdminClient;
use NetBS\AuthBundle\Service\HydraClientException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ConsentChallengeController extends AbstractController
{
    public function __construct(
        private readonly HydraAdminClient $hydra,
        private readonly IdentityUserResolverInterface $userResolver,
        private readonly IdentityClientPolicyInterface $policy,
        private readonly ClaimsAssembler $claims,
        private readonly LoggerInterface $logger,
    ) {
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

        $clientName = $consentRequest['client']['client_name'] ?? '';
        $clientName = \is_string($clientName) && $clientName !== '' ? $clientName : $clientId;

        $identity = $this->userResolver->resolveBySub($subject);
        if ($identity === null || $identity->isDisabled) {
            return $this->rejectAndShowError($consentChallenge, $clientName, 'User not found or disabled', [
                'subject'   => $subject,
                'client_id' => $clientId,
                'reason'    => $identity === null ? 'unknown_subject' : 'disabled',
            ]);
        }

        // Access gate: the user exists, isn't disabled, and has the account
        // flag the client requires (`metadata.required_flag`, set on the OIDC
        // admin form). Every decision is logged.
        if (!$this->policy->canAccess($identity, $clientId)) {
            return $this->rejectAndShowError($consentChallenge, $clientName, "User does not have access to {$clientId}", [
                'subject'   => $subject,
                'client_id' => $clientId,
                'reason'    => 'policy_denied',
            ]);
        }

        $accept = $this->hydra->acceptConsentRequest($consentChallenge, [
            'grant_scope' => $requestedScopes,
            'grant_access_token_audience' => $consentRequest['requested_access_token_audience'] ?? [],
            'remember' => true,
            'remember_for' => 3600 * 12,
            'session' => $this->claims->hydraSession($identity, \is_array($requestedScopes) ? $requestedScopes : []),
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
     * Access denials end on netBS's error page rather than back at the client:
     * the client only gets a bare `error=access_denied` and shows a generic
     * failure, while here we can tell the user which app refused them. The
     * challenge is still rejected so Hydra closes it.
     *
     * @param array<string, mixed> $logContext extra structured fields for the warning log
     */
    private function rejectAndShowError(string $consentChallenge, string $clientName, string $description, array $logContext = []): Response
    {
        $this->logger->warning('oidc.consent: rejected', $logContext + [
            'consent_challenge' => $consentChallenge,
            'decision'          => 'reject',
            'error'             => 'access_denied',
            'error_description' => $description,
        ]);

        try {
            $this->hydra->rejectConsentRequest($consentChallenge, [
                'error' => 'access_denied',
                'error_description' => $description,
            ]);
        } catch (HydraClientException $e) {
            $this->logger->critical('oidc.consent: reject call failed', [
                'consent_challenge' => $consentChallenge,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->render('@NetBSAuth/identity/error.html.twig', [
            'errorCode'   => 'access_denied',
            'userMessage' => "Vous n'avez pas accès à {$clientName}.",
            'devDetails'  => null,
        ], new Response('', Response::HTTP_FORBIDDEN));
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
