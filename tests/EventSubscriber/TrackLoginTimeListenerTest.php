<?php

namespace App\Tests\EventSubscriber;

use NetBS\SecureBundle\EventSubscriber\SessionInvalidationListener;
use NetBS\SecureBundle\EventSubscriber\TrackLoginTimeListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class TrackLoginTimeListenerTest extends TestCase
{
    public function test_records_login_time_when_request_has_a_session(): void
    {
        $session = new Session(new MockArraySessionStorage());
        $session->start();
        $request = Request::create('/');
        $request->setSession($session);

        $stack = new RequestStack();
        $stack->push($request);

        $before = (new \DateTimeImmutable())->getTimestamp();
        (new TrackLoginTimeListener($stack))->onLoginSuccess($this->event());
        $after = (new \DateTimeImmutable())->getTimestamp();

        $stored = $session->get(SessionInvalidationListener::LOGIN_TIME_KEY);
        $this->assertNotNull($stored, 'Login time must be written under the key the invalidation listener reads.');
        $this->assertGreaterThanOrEqual($before, $stored);
        $this->assertLessThanOrEqual($after, $stored);
    }

    public function test_stateless_request_without_session_is_a_noop_and_does_not_throw(): void
    {
        // Mirrors a JWT/json_login firewall: a request with no session attached.
        // The old code called getSession() unconditionally and 500'd here.
        $request = Request::create('/api/v1/netBS/something');
        $this->assertFalse($request->hasSession());

        $stack = new RequestStack();
        $stack->push($request);

        (new TrackLoginTimeListener($stack))->onLoginSuccess($this->event());

        $this->assertFalse($request->hasSession(), 'Listener must not create or touch a session on stateless requests.');
    }

    public function test_empty_request_stack_is_a_noop_and_does_not_throw(): void
    {
        (new TrackLoginTimeListener(new RequestStack()))->onLoginSuccess($this->event());

        $this->addToAssertionCount(1); // reaching here without an exception is the assertion
    }

    private function event(): LoginSuccessEvent
    {
        // The listener ignores the event entirely (it reads from the RequestStack),
        // so a mock is enough and avoids constructing the heavy event graph.
        return $this->createMock(LoginSuccessEvent::class);
    }
}
