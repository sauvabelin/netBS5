<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Service\HydraAdminClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class LoginChallengeController extends AbstractController
{
    public function __construct(private readonly HydraAdminClient $hydra)
    {
    }

    #[Route('/oidc-login', name: 'oidc_login', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $loginChallenge = $request->query->get('login_challenge');
        if (!\is_string($loginChallenge) || $loginChallenge === '') {
            throw new \InvalidArgumentException('login_challenge missing');
        }

        $loginRequest = $this->hydra->getLoginRequest($loginChallenge);

        $user = $this->getUser();
        if (!$user) {
            // The firewall should already have redirected to /login before
            // reaching this controller. If we got here without a user, it's a config bug.
            throw new AccessDeniedException();
        }

        $accept = $this->hydra->acceptLoginRequest($loginChallenge, [
            'subject' => $user->getUserIdentifier(),
            'remember' => true,
            'remember_for' => 3600 * 12,
        ]);

        return new RedirectResponse($accept['redirect_to']);
    }
}
