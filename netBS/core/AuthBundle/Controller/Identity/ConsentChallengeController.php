<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Controller\Identity;

use NetBS\AuthBundle\Contract\IdentityClientPolicyInterface;
use NetBS\AuthBundle\Contract\IdentityUserResolverInterface;
use NetBS\AuthBundle\Service\ClaimsAssembler;
use NetBS\AuthBundle\Service\HydraAdminClient;
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
    ) {
    }

    #[Route('/oidc-consent', name: 'oidc_consent', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $consentChallenge = $request->query->get('consent_challenge');
        if (!\is_string($consentChallenge) || $consentChallenge === '') {
            throw new \InvalidArgumentException('consent_challenge missing');
        }

        $consentRequest = $this->hydra->getConsentRequest($consentChallenge);
        $subject = $consentRequest['subject'];
        $clientId = $consentRequest['client']['client_id'];
        $requestedScopes = $consentRequest['requested_scope'] ?? [];

        $identity = $this->userResolver->resolveBySub($subject);
        if ($identity === null || $identity->isDisabled) {
            $reject = $this->hydra->rejectConsentRequest($consentChallenge, [
                'error' => 'access_denied',
                'error_description' => 'User not found or disabled',
            ]);
            return new RedirectResponse($reject['redirect_to']);
        }

        if (!$this->policy->canAccess($identity, $clientId)) {
            $reject = $this->hydra->rejectConsentRequest($consentChallenge, [
                'error' => 'access_denied',
                'error_description' => "User does not have access to {$clientId}",
            ]);
            return new RedirectResponse($reject['redirect_to']);
        }

        $idTokenClaims = $this->claims->assemble($identity, $clientId);

        $accept = $this->hydra->acceptConsentRequest($consentChallenge, [
            'grant_scope' => $requestedScopes,
            'grant_access_token_audience' => $consentRequest['requested_access_token_audience'] ?? [],
            'remember' => true,
            'remember_for' => 3600 * 12,
            'session' => [
                'id_token' => $idTokenClaims,
                'access_token' => [],
            ],
        ]);

        return new RedirectResponse($accept['redirect_to']);
    }
}
