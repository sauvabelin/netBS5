<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Listener;

use NetBS\AuthBundle\Listener\RevokeSsoOnLogoutListener;
use NetBS\AuthBundle\Service\HydraAdminClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class RevokeSsoOnLogoutListenerTest extends TestCase
{
    private function listener(MockHttpClient $http): RevokeSsoOnLogoutListener
    {
        $hydra = new HydraAdminClient($http, new \Psr\Log\NullLogger());
        return new RevokeSsoOnLogoutListener($hydra, new \Psr\Log\NullLogger());
    }

    private function event(Request $request): LogoutEvent
    {
        return new LogoutEvent($request, new UsernamePasswordToken(new InMemoryUser('Equipe', null), 'netbs'));
    }

    public function testNetbsLogoutRevokesTheSubjectsLoginSessions(): void
    {
        $http = new MockHttpClient(function (string $method, string $url) {
            $this->assertSame('DELETE', $method);
            $this->assertStringContainsString('/admin/oauth2/auth/sessions/login?subject=equipe', $url);
            return new MockResponse('', ['http_code' => 204]);
        });

        ($this->listener($http))($this->event(Request::create('/netBS/secure/logout')));

        $this->assertSame(1, $http->getRequestsCount());
    }

    public function testRpInitiatedLogoutIsLeftToHydra(): void
    {
        $http = new MockHttpClient([]);

        ($this->listener($http))($this->event(Request::create('/oidc-logout', 'POST', ['logout_challenge' => 'ch-1'])));

        $this->assertSame(0, $http->getRequestsCount());
    }

    public function testHydraFailureDoesNotBlockLogout(): void
    {
        $http = new MockHttpClient([new MockResponse('down', ['http_code' => 503])]);

        ($this->listener($http))($this->event(Request::create('/netBS/secure/logout')));

        $this->assertSame(1, $http->getRequestsCount());
    }

    public function testForcedReauthenticationKeepsSsoSessions(): void
    {
        $http = new MockHttpClient([]);

        ($this->listener($http))($this->event(Request::create('/oidc-login', 'GET', ['login_challenge' => 'ch-1'])));

        $this->assertSame(0, $http->getRequestsCount());
    }
}
