<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Controller\Identity;

use NetBS\AuthBundle\Service\HydraAdminClient;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class LoginChallengeController extends AbstractController
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly HydraAdminClient $hydra,
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

                $reject = $this->hydra->rejectLoginRequest($loginChallenge, [
                    'error' => 'login_required',
                    'error_description' => 'Existing Hydra session does not match current netBS user. Re-authentication required.',
                ]);

                return new RedirectResponse($reject['redirect_to']);
            }
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
}
