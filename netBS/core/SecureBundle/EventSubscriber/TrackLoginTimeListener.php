<?php

namespace NetBS\SecureBundle\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class TrackLoginTimeListener implements EventSubscriberInterface
{
    public function __construct(private readonly RequestStack $requests) {}

    public static function getSubscribedEvents(): array
    {
        return [LoginSuccessEvent::class => 'onLoginSuccess'];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        // LoginSuccessEvent fires on every firewall, including the stateless
        // JWT/json_login ones, which have no session — getSession() would throw
        // SessionNotFoundException there. Only record a login time when a real
        // session exists (mirrors SessionInvalidationListener's hasSession guard).
        $request = $this->requests->getMainRequest();
        if ($request === null || !$request->hasSession()) {
            return;
        }

        $request->getSession()->set(
            SessionInvalidationListener::LOGIN_TIME_KEY,
            (new \DateTimeImmutable())->getTimestamp(),
        );
    }
}
