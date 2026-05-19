<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Service\HydraAdminClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ConsentChallengeController extends AbstractController
{
    public function __construct(private readonly HydraAdminClient $hydra)
    {
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
        $requestedScopes = $consentRequest['requested_scope'] ?? [];

        // 0c skeleton: hardcoded claims. Real ClaimsAssembler comes in 0d.
        $idTokenClaims = [
            'sub' => $subject,
            'preferred_username' => $subject,
            'email' => 'placeholder@example.org',
            'name' => $subject,
        ];

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
