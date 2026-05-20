<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Controller\Identity;

use NetBS\AuthBundle\Service\HydraAdminClient;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Handles the Hydra logout-challenge round-trip.
 *
 * Flow:
 *   - GET  /oidc-logout?logout_challenge=…  renders a confirmation page.
 *     We do NOT log the user out on GET so a drive-by <img src> can't
 *     terminate someone's session.
 *   - POST /oidc-logout                    validates CSRF + (when a user is
 *     logged in) the logout_request.subject matches the current session,
 *     then kills the local Symfony session FIRST and only then tells Hydra
 *     to accept the logout. If Hydra is unreachable we still redirect the
 *     user somewhere sane — the local session is already gone, which is
 *     the safer outcome.
 */
final class LogoutController extends AbstractController
{
    private const CSRF_TOKEN_ID = 'oidc_logout';

    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly HydraAdminClient $hydra,
        private readonly Security $security,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    #[Route('/oidc-logout', name: 'oidc_logout', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $logoutChallenge = (string) $request->get('logout_challenge', '');

        // No challenge: this is a plain "log me out of netBS" hit. Treat as
        // a same-origin confirm-then-logout, never auto-act on GET.
        if ($logoutChallenge === '') {
            if ($request->isMethod('POST')) {
                if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, (string) $request->request->get('_token'))) {
                    throw $this->createAccessDeniedException('Invalid CSRF token.');
                }
                $this->security->logout(validateCsrfToken: false);
                return $this->redirectToRoute('netbs.core.home.dashboard');
            }

            return $this->render('@NetBSAuth/identity/logout_confirm.html.twig', [
                'logoutChallenge' => null,
                'csrfTokenId'     => self::CSRF_TOKEN_ID,
            ]);
        }

        // Fetch the logout request up front so we can show the user which
        // session is about to be terminated and verify the subject.
        try {
            $logoutRequest = $this->hydra->getLogoutRequest($logoutChallenge);
        } catch (\Throwable $e) {
            $this->logger->warning('Hydra getLogoutRequest failed', [
                'challenge' => $logoutChallenge,
                'exception' => $e->getMessage(),
            ]);
            return $this->redirectToRoute('oidc_error', ['error_description' => 'logout_challenge invalid or expired']);
        }

        $hydraSubject  = isset($logoutRequest['subject']) ? (string) $logoutRequest['subject'] : '';
        $currentUser   = $this->getUser();
        $currentSubject = $currentUser?->getUserIdentifier();

        // If a user is logged in locally, the subject Hydra wants to log out
        // must match. Otherwise an attacker who can craft a logout_challenge
        // for some other identity could trick us into ending an unrelated
        // session.
        if ($currentSubject !== null && $hydraSubject !== '' && $hydraSubject !== $currentSubject) {
            $this->logger->warning('OIDC logout subject mismatch', [
                'challenge'      => $logoutChallenge,
                'hydra_subject'  => $hydraSubject,
                'session_subject'=> $currentSubject,
            ]);
            throw $this->createAccessDeniedException('Logout subject does not match the current session.');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid(self::CSRF_TOKEN_ID, (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            // 1) Kill the local session FIRST. If anything below explodes the
            //    safer state is "logged out locally" rather than "still
            //    logged in but a stack trace on screen".
            $this->security->logout(validateCsrfToken: false);

            // 2) Now tell Hydra to finalise its end of the logout. Failures
            //    are logged but don't bubble up — the user already saw the
            //    Logout button work from their perspective.
            try {
                $accept = $this->hydra->acceptLogoutRequest($logoutChallenge);
                if (isset($accept['redirect_to']) && \is_string($accept['redirect_to']) && $accept['redirect_to'] !== '') {
                    return new RedirectResponse($accept['redirect_to']);
                }
            } catch (\Throwable $e) {
                $this->logger->error('Hydra acceptLogoutRequest failed; local session already cleared', [
                    'challenge' => $logoutChallenge,
                    'subject'   => $hydraSubject,
                    'exception' => $e->getMessage(),
                ]);
            }

            return $this->redirectToRoute('netbs.secure.login.login');
        }

        // Hydra returns the OAuth client that initiated the logout under
        // `client` (when the logout was RP-initiated). Pass it through so the
        // shared auth layout can render the client's branding — same logo/
        // name treatment as the login page.
        $oidcClient = (isset($logoutRequest['client']) && \is_array($logoutRequest['client']))
            ? $logoutRequest['client']
            : null;

        return $this->render('@NetBSAuth/identity/logout_confirm.html.twig', [
            'logoutChallenge' => $logoutChallenge,
            'csrfTokenId'     => self::CSRF_TOKEN_ID,
            'subject'         => $hydraSubject,
            'oidc_client'     => $oidcClient,
        ]);
    }
}
