<?php

namespace App\Tests\EventSubscriber;

use App\Entity\BSUser;
use NetBS\SecureBundle\EventSubscriber\SessionInvalidationListener;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class SessionInvalidationListenerTest extends TestCase
{
    public function test_session_older_than_passwordChangedAt_is_invalidated(): void
    {
        $user = new BSUser();
        $user->setPasswordChangedAt(new \DateTimeImmutable('2026-05-01 10:00:00'));

        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $session->set(SessionInvalidationListener::LOGIN_TIME_KEY, (new \DateTimeImmutable('2026-04-30 09:00:00'))->getTimestamp());

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $listener = new SessionInvalidationListener($security);

        $request = Request::create('/');
        $request->setSession($session);

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $listener->onRequest($event);

        $this->assertEmpty($session->all(), 'Session should have been invalidated (cleared).');
    }

    public function test_session_logged_in_same_second_as_password_change_is_invalidated(): void
    {
        $changedAt = new \DateTimeImmutable('2026-05-01 10:00:00');
        $user = new BSUser();
        $user->setPasswordChangedAt($changedAt);

        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $session->set(SessionInvalidationListener::LOGIN_TIME_KEY, $changedAt->getTimestamp());

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $listener = new SessionInvalidationListener($security);

        $request = Request::create('/');
        $request->setSession($session);

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $listener->onRequest($event);

        $this->assertEmpty($session->all(), 'Same-second session must be invalidated (second-resolution race).');
    }

    public function test_fresh_session_is_left_alone(): void
    {
        $user = new BSUser();
        $user->setPasswordChangedAt(new \DateTimeImmutable('2026-05-01 10:00:00'));

        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $session->set(SessionInvalidationListener::LOGIN_TIME_KEY, (new \DateTimeImmutable('2026-05-02 09:00:00'))->getTimestamp());

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $listener = new SessionInvalidationListener($security);

        $request = Request::create('/');
        $request->setSession($session);

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $listener->onRequest($event);

        $this->assertNotEmpty($session->all(), 'Fresh session should not be invalidated.');
    }

    public function test_anonymous_request_is_skipped(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $session->set(SessionInvalidationListener::LOGIN_TIME_KEY, 0);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        $listener = new SessionInvalidationListener($security);

        $request = Request::create('/');
        $request->setSession($session);

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $listener->onRequest($event);

        $this->assertNotEmpty($session->all(), 'Anonymous session should not be touched.');
    }

    public function test_user_without_passwordChangedAt_is_skipped(): void
    {
        $user = new BSUser();
        // no passwordChangedAt set
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $session->set(SessionInvalidationListener::LOGIN_TIME_KEY, 0);

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $listener = new SessionInvalidationListener($security);

        $request = Request::create('/');
        $request->setSession($session);

        $event = new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );

        $listener->onRequest($event);

        $this->assertNotEmpty($session->all(), 'Pre-feature user with no passwordChangedAt should not be touched.');
    }
}
