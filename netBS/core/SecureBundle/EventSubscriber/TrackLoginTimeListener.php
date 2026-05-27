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
        $session = $this->requests->getSession();
        $session->set(SessionInvalidationListener::LOGIN_TIME_KEY, (new \DateTimeImmutable())->getTimestamp());
    }
}
