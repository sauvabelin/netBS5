<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Controller\Identity;

use NetBS\AuthBundle\Service\HydraAdminClient;
use NetBS\AuthBundle\Service\HydraClientException;
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
    /**
     * CSRF token id used by the plain "log me out of netBS" POST form (no
     * Hydra challenge). Distinct from the per-challenge id used below so a
     * token minted for one flow cannot be replayed against the other.
     */
    private const CSRF_TOKEN_ID_LOCAL = 'oidc_logout_local';

    /**
     * Per-challenge CSRF token id prefix. The full id is
     * `oidc_logout_challenge_<challenge>` so each Hydra logout round-trip has
     * its own token namespace.
     */
    private const CSRF_TOKEN_ID_CHALLENGE_PREFIX = 'oidc_logout_challenge_';

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
            $csrfTokenId = self::CSRF_TOKEN_ID_LOCAL;
            if ($request->isMethod('POST')) {
                if (!$this->isCsrfTokenValid($csrfTokenId, (string) $request->request->get('_token'))) {
                    throw $this->createAccessDeniedException('Invalid CSRF token.');
                }
                $this->security->logout(validateCsrfToken: false);
                return $this->redirectToRoute('netbs.core.home.dashboard');
            }

            return $this->render('@NetBSAuth/identity/logout_confirm.html.twig', [
                'logoutChallenge' => null,
                'csrfTokenId'     => $csrfTokenId,
            ]);
        }

        // Distinct token id per challenge: a CSRF token minted for the plain
        // logout flow above cannot be replayed here, and tokens are scoped to
        // the specific challenge the form was rendered with.
        $csrfTokenId = self::CSRF_TOKEN_ID_CHALLENGE_PREFIX . $logoutChallenge;

        // Fetch the logout request up front so we can show the user which
        // session is about to be terminated and verify the subject. We only
        // catch HydraClientException here — programmer errors (TypeError,
        // autoloader failures, etc.) must bubble up to Symfony's exception
        // listener so they don't get swallowed as "challenge expired".
        try {
            $logoutRequest = $this->hydra->getLogoutRequest($logoutChallenge);
        } catch (HydraClientException $e) {
            $this->logger->warning('Hydra getLogoutRequest failed', [
                'challenge' => $logoutChallenge,
                'exception' => $e->getMessage(),
            ]);
            return $this->redirectToRoute('oidc_error', ['error_description' => 'logout_challenge invalid or expired']);
        }

        $hydraSubject  = isset($logoutRequest['subject']) ? (string) $logoutRequest['subject'] : '';
        $currentUser   = $this->getUser();
        $currentSubject = $currentUser?->getUserIdentifier();

        // The Hydra session and the local netBS session are independent. If
        // they disagree on identity (e.g. user is locally `iacopo` but Hydra
        // still has a session for `admin`), accepting Hydra's logout while
        // the local user is signed in lets an attacker weaponise the local
        // session: they craft a logout_challenge for VICTIM, trick a logged-in
        // netBS user into POSTing it, and Hydra terminates the victim's
        // session + all federated RPs — all under cover of a perfectly valid
        // CSRF token. We refuse the request entirely in that case.
        $subjectMismatch = (
            $currentSubject !== null
            && $hydraSubject !== ''
            && $hydraSubject !== $currentSubject
        );

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid($csrfTokenId, (string) $request->request->get('_token'))) {
                throw $this->createAccessDeniedException('Invalid CSRF token.');
            }

            if ($subjectMismatch) {
                // Confused-deputy refusal: a local user is authenticated but
                // the Hydra logout_challenge was issued for a DIFFERENT
                // subject. We must NOT accept the logout — doing so would
                // let the attacker terminate the victim's Hydra session +
                // back-channel-logout RPs by tricking any logged-in admin
                // into clicking the confirmation form. Render an error page
                // explaining the situation; do not call acceptLogoutRequest.
                $this->logger->warning('oidc.logout: refused - subject mismatch with active local session', [
                    'challenge'      => $logoutChallenge,
                    'hydra_subject'  => $hydraSubject,
                    'session_subject'=> $currentSubject,
                    'decision'       => 'refuse',
                    'reason'         => 'subject_mismatch_with_local_user',
                ]);

                return $this->render('@NetBSAuth/identity/logout_confirm.html.twig', [
                    'logoutChallenge'   => $logoutChallenge,
                    'csrfTokenId'       => $csrfTokenId,
                    'subject'           => $hydraSubject,
                    'oidc_client'       => (isset($logoutRequest['client']) && \is_array($logoutRequest['client']))
                        ? $logoutRequest['client']
                        : null,
                    'subjectMismatch'   => true,
                    'localSubject'      => $currentSubject,
                    'mismatchRefused'   => true,
                ]);
            }

            // 1) Kill the local session FIRST. If anything below explodes the
            //    safer state is "logged out locally" rather than "still
            //    logged in but a stack trace on screen".
            $this->security->logout(validateCsrfToken: false);

            // 2) Now tell Hydra to finalise its end of the logout. Failures
            //    are logged but don't bubble up — the user already saw the
            //    Logout button work from their perspective. We narrow the
            //    catch to HydraClientException so genuine programmer errors
            //    still surface to Symfony's exception listener.
            try {
                $accept = $this->hydra->acceptLogoutRequest($logoutChallenge);
                if (isset($accept['redirect_to']) && \is_string($accept['redirect_to']) && $accept['redirect_to'] !== '') {
                    return new RedirectResponse($accept['redirect_to']);
                }
            } catch (HydraClientException $e) {
                $this->logger->error('Hydra acceptLogoutRequest failed', [
                    'challenge'        => $logoutChallenge,
                    'subject'          => $hydraSubject,
                    'exception'        => $e->getMessage(),
                ]);
                // Tell the user that the local part worked but the SSO server
                // didn't acknowledge — they may need to log out manually from
                // other federated services.
                $this->addFlash(
                    'warning',
                    'Local logout succeeded but the SSO server did not respond. '
                    . 'You may still be signed in to other connected services.'
                );
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
            'csrfTokenId'     => $csrfTokenId,
            'subject'         => $hydraSubject,
            'oidc_client'     => $oidcClient,
            'subjectMismatch' => $subjectMismatch,
            'localSubject'    => $currentSubject,
            'mismatchRefused' => false,
        ]);
    }
}
