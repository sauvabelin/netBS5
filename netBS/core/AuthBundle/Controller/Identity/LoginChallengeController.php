<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Controller\Identity;

use NetBS\AuthBundle\Listener\RecordAuthenticationTimeListener;
use NetBS\AuthBundle\Security\LocalSessionTerminator;
use NetBS\AuthBundle\Service\HydraAdminClient;
use NetBS\AuthBundle\Service\HydraClientException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class LoginChallengeController extends AbstractController
{
    /** Session key prefix: when re-authentication was forced for a given challenge. */
    private const REAUTH_KEY_PREFIX = '_oidc.reauth_requested_at.';

    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly HydraAdminClient $hydra,
        private readonly LocalSessionTerminator $localSession,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    #[Route('/oidc-login', name: 'oidc_login', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $loginChallenge = $request->query->get('login_challenge');
        if (!\is_string($loginChallenge) || $loginChallenge === '') {
            $this->logger->warning('oidc.login: rejected - missing challenge', [
                'decision'  => 'reject',
                'reason'    => 'missing_challenge',
            ]);

            throw new \InvalidArgumentException('login_challenge missing');
        }

        $loginRequest = $this->hydra->getLoginRequest($loginChallenge);
        $clientId = $loginRequest['client']['client_id'] ?? null;

        $user = $this->getUser();
        if (!$user) {
            // The firewall should already have redirected to /login before
            // reaching this controller. If we got here without a user, it's a config bug.
            $this->logger->warning('oidc.login: rejected - no authenticated user', [
                'login_challenge' => $loginChallenge,
                'client_id'       => $clientId,
                'decision'        => 'reject',
                'reason'          => 'no_authenticated_user',
            ]);

            throw new AccessDeniedException();
        }

        $currentSub = $user->getUserIdentifier();

        // An admin impersonating someone (switch_user) must not be able to
        // obtain RP tokens in that person's name; nothing in the token would
        // show the impersonation.
        if ($this->container->get('security.token_storage')->getToken() instanceof SwitchUserToken) {
            $this->logger->warning('oidc.login: rejected - impersonated session', [
                'subject'         => $currentSub,
                'client_id'       => $clientId,
                'login_challenge' => $loginChallenge,
                'decision'        => 'reject',
                'reason'          => 'switch_user',
            ]);

            $reject = $this->hydra->rejectLoginRequest($loginChallenge, [
                'error' => 'access_denied',
                'error_description' => 'Impersonated sessions cannot sign in to other applications.',
            ]);

            return new RedirectResponse($reject['redirect_to']);
        }

        // Hydra signals `skip: true` when it already has a valid login session
        // for a subject — the IdP should accept without re-prompting. But the
        // subject Hydra remembers is the one from a previous auth round; the
        // Symfony session may belong to a different user now (e.g. Alice
        // logged out of Hydra-tracked Bob's account, then logged into netBS
        // as Alice). Blindly accepting with `subject = current Symfony user`
        // silently re-binds Hydra's session to the new user — a cross-account
        // token forgery vector. When the subjects disagree, reject this
        // login round (and prompt re-auth) instead of papering over it.
        if (($loginRequest['skip'] ?? false) === true) {
            $hydraSub = $loginRequest['subject'] ?? null;
            if (!\is_string($hydraSub) || !hash_equals($hydraSub, $currentSub)) {
                $this->logger->warning('oidc.login: subject mismatch on skip=true; rejecting', [
                    'subject'         => $currentSub,
                    'hydra_subject'   => $hydraSub,
                    'session_subject' => $currentSub,
                    'client_id'       => $clientId,
                    'login_challenge' => $loginChallenge,
                    'decision'        => 'reject',
                    'reason'          => 'subject_mismatch_on_skip',
                ]);

                // End the stale Hydra login session for this browser, otherwise
                // every RP login keeps hitting this mismatch until it expires.
                $this->revokeHydraSession($loginRequest['session_id'] ?? null, $loginChallenge);

                $reject = $this->hydra->rejectLoginRequest($loginChallenge, [
                    'error' => 'login_required',
                    'error_description' => 'Existing Hydra session does not match current netBS user. Re-authentication required.',
                ]);

                return new RedirectResponse($reject['redirect_to']);
            }
        }

        // `prompt=login` / `max_age`: the RP wants proof of a recent password
        // entry. Hydra delegates that to us (skip=false) and stamps auth_time
        // with the time we accept, so accepting on an old netBS session would
        // be a lie. Hydra only sets skip=true when its own session satisfies
        // the request, so that path needs no check.
        if (($loginRequest['skip'] ?? false) !== true && $this->needsReauthentication($request, $loginRequest, $loginChallenge)) {
            if (\in_array('none', $this->promptValues($loginRequest), true)) {
                $reject = $this->hydra->rejectLoginRequest($loginChallenge, [
                    'error' => 'login_required',
                    'error_description' => 'Re-authentication required but prompt=none was requested.',
                ]);

                return new RedirectResponse($reject['redirect_to']);
            }

            $this->logger->info('oidc.login: forcing re-authentication', [
                'subject'         => $currentSub,
                'client_id'       => $clientId,
                'login_challenge' => $loginChallenge,
            ]);

            return $this->forceReauthentication($request, $loginChallenge);
        }

        $accept = $this->hydra->acceptLoginRequest($loginChallenge, [
            'subject' => $currentSub,
            'remember' => true,
            'remember_for' => 3600 * 12,
        ]);

        $this->logger->info('oidc.login: accepted', [
            'subject'         => $currentSub,
            'client_id'       => $clientId,
            'login_challenge' => $loginChallenge,
            'decision'        => 'accept',
            'reason'          => ($loginRequest['skip'] ?? false) ? 'skip_existing_session' : 'authenticated_session',
        ]);

        return new RedirectResponse($accept['redirect_to']);
    }

    /**
     * @param array<string, mixed> $loginRequest
     */
    private function needsReauthentication(Request $request, array $loginRequest, string $loginChallenge): bool
    {
        $session = $request->hasSession() ? $request->getSession() : null;
        $authenticatedAt = $session?->get(RecordAuthenticationTimeListener::SESSION_KEY);
        $authenticatedAt = \is_int($authenticatedAt) ? $authenticatedAt : null;

        if (\in_array('login', $this->promptValues($loginRequest), true)) {
            // Satisfied once the user has logged in again after we asked.
            $requestedAt = $session?->get(self::REAUTH_KEY_PREFIX . $loginChallenge);
            if (!\is_int($requestedAt) || $authenticatedAt === null || $authenticatedAt < $requestedAt) {
                return true;
            }
        }

        $maxAge = $this->requestParameter($loginRequest, 'max_age');
        if ($maxAge !== null && ctype_digit($maxAge)) {
            if ($authenticatedAt === null || time() - $authenticatedAt > (int) $maxAge) {
                return true;
            }
        }

        return false;
    }

    /**
     * Drops the local session and sends the user to the login form; the
     * oidc firewall's target path brings them back to this challenge after.
     */
    private function forceReauthentication(Request $request, string $loginChallenge): Response
    {
        $this->localSession->terminate($request);

        if ($request->hasSession()) {
            $session = $request->getSession();
            $session->set(self::REAUTH_KEY_PREFIX . $loginChallenge, time());
            $session->set('_security.oidc.target_path', $request->getUri());
        }

        return $this->localSession->clearRememberMe($this->redirectToRoute('netbs.secure.login.login'));
    }

    /**
     * @param array<string, mixed> $loginRequest
     * @return list<string>
     */
    private function promptValues(array $loginRequest): array
    {
        $prompt = $this->requestParameter($loginRequest, 'prompt');
        return $prompt === null ? [] : array_values(array_filter(explode(' ', $prompt)));
    }

    /**
     * Reads a parameter of the RP's original authorization request, which
     * Hydra hands us verbatim as `request_url`.
     *
     * @param array<string, mixed> $loginRequest
     */
    private function requestParameter(array $loginRequest, string $name): ?string
    {
        $url = $loginRequest['request_url'] ?? null;
        if (!\is_string($url)) {
            return null;
        }
        parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
        $value = $query[$name] ?? null;
        return \is_string($value) ? $value : null;
    }

    private function revokeHydraSession(mixed $sessionId, string $loginChallenge): void
    {
        if (!\is_string($sessionId) || $sessionId === '') {
            return;
        }
        try {
            $this->hydra->revokeLoginSession($sessionId);
        } catch (HydraClientException $e) {
            $this->logger->warning('oidc.login: could not revoke stale Hydra login session', [
                'login_challenge' => $loginChallenge,
                'error'           => $e->getMessage(),
            ]);
        }
    }
}
