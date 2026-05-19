<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Controller\Identity;

use NetBS\AuthBundle\Contract\IdentityClientPolicyInterface;
use NetBS\AuthBundle\Contract\IdentityUserResolverInterface;
use NetBS\AuthBundle\Service\ClaimsAssembler;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class RefreshHookController extends AbstractController
{
    public function __construct(
        private readonly IdentityUserResolverInterface $userResolver,
        private readonly IdentityClientPolicyInterface $policy,
        private readonly ClaimsAssembler $claims,
    ) {
    }

    #[Route('/oidc-refresh-hook', name: 'oidc_refresh_hook', methods: ['POST'])]
    public function __invoke(Request $request): Response
    {
        $payload = json_decode($request->getContent(), true, flags: \JSON_THROW_ON_ERROR);

        $subject = $payload['subject'] ?? null;
        $clientId = $payload['client_id'] ?? ($payload['client']['client_id'] ?? null);

        if (!\is_string($subject) || !\is_string($clientId)) {
            return new JsonResponse(['error' => 'invalid_request'], Response::HTTP_BAD_REQUEST);
        }

        $identity = $this->userResolver->resolveBySub($subject);
        if ($identity === null || $identity->isDisabled || !$this->policy->canAccess($identity, $clientId)) {
            return new JsonResponse(['error' => 'access_denied'], Response::HTTP_FORBIDDEN);
        }

        $idTokenClaims = $this->claims->assemble($identity, $clientId);

        return new JsonResponse([
            'session' => [
                'id_token' => $idTokenClaims,
                'access_token' => [],
            ],
        ]);
    }
}
