<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Listener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Authenticator\RememberMeAuthenticator;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Remembers when the user last typed their password, so the OIDC login
 * challenge can honour `max_age` and `prompt=login` (Symfony tokens don't
 * carry an authentication time). Remember-me logins don't count.
 */
#[AsEventListener(event: LoginSuccessEvent::class)]
final class RecordAuthenticationTimeListener
{
    public const SESSION_KEY = '_oidc.authenticated_at';

    public function __invoke(LoginSuccessEvent $event): void
    {
        if ($event->getAuthenticator() instanceof RememberMeAuthenticator) {
            return;
        }

        $request = $event->getRequest();
        if ($request->hasSession()) {
            $request->getSession()->set(self::SESSION_KEY, time());
        }
    }
}
