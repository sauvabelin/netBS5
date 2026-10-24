<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Listener;

use NetBS\AuthBundle\Contract\Subject;
use NetBS\AuthBundle\Service\HydraAdminClient;
use NetBS\AuthBundle\Service\HydraClientException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LogoutEvent;

/**
 * Logging out of netBS also ends the user's Hydra login sessions. Otherwise
 * Hydra keeps the old subject for `remember_for` (12h): the next person to
 * log in on the same browser hits a subject mismatch on every RP login, and
 * the logged-out user would be waved through by `skip=true` on the next RP.
 *
 * Best effort: a Hydra outage must never block a logout.
 */
#[AsEventListener(event: LogoutEvent::class)]
final class RevokeSsoOnLogoutListener
{
    public function __construct(
        private readonly HydraAdminClient $hydra,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(LogoutEvent $event): void
    {
        // An RP-initiated logout is finished by Hydra itself (acceptLogoutRequest
        // ends that login session); revoking it first would break the round-trip.
        if ($event->getRequest()->get('logout_challenge')) {
            return;
        }

        $identifier = $event->getToken()?->getUserIdentifier();
        if ($identifier === null || $identifier === '') {
            return;
        }
        $subject = Subject::fromUsername($identifier);

        try {
            $this->hydra->revokeLoginSessionsForSubject($subject);
        } catch (HydraClientException $e) {
            $this->logger->warning('oidc.logout: could not revoke Hydra login sessions', [
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
