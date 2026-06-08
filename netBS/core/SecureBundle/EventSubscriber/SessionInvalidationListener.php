<?php

namespace NetBS\SecureBundle\EventSubscriber;

use NetBS\SecureBundle\Mapping\BaseUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class SessionInvalidationListener implements EventSubscriberInterface
{
    public const LOGIN_TIME_KEY = '_netbs_login_time';

    public function __construct(private readonly Security $security) {}

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => ['onRequest', 6]];
    }

    public function onRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof BaseUser) {
            return;
        }

        $changedAt = $user->getPasswordChangedAt();
        if (!$changedAt) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        $loginTs = $session->get(self::LOGIN_TIME_KEY);
        if ($loginTs === null) {
            return;
        }

        // <= so a session that logged in within the same second as the password
        // change is also invalidated — both timestamps are second-resolution.
        if ($loginTs <= $changedAt->getTimestamp()) {
            $session->invalidate();
        }
    }
}
