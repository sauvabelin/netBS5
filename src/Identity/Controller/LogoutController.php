<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Service\HydraAdminClient;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class LogoutController extends AbstractController
{
    public function __construct(
        private readonly HydraAdminClient $hydra,
        private readonly Security $security,
    ) {
    }

    #[Route('/oidc-logout', name: 'oidc_logout', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $logoutChallenge = $request->query->get('logout_challenge');
        if (!\is_string($logoutChallenge) || $logoutChallenge === '') {
            $this->security->logout(validateCsrfToken: false);
            return $this->redirectToRoute('netbs.core.home.dashboard');
        }

        $accept = $this->hydra->acceptLogoutRequest($logoutChallenge);
        $this->security->logout(validateCsrfToken: false);
        return new RedirectResponse($accept['redirect_to']);
    }
}
