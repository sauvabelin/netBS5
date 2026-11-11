<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Listener;

use NetBS\AuthBundle\Listener\RecordAuthenticationTimeListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\RememberMeAuthenticator;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * The recorded authentication time backs OIDC max_age and prompt=login, so a
 * remember-me cookie must not count as a fresh authentication.
 */
final class RecordAuthenticationTimeListenerTest extends TestCase
{
    private function login(string $authenticatorClass): Request
    {
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));

        (new RecordAuthenticationTimeListener())(new LoginSuccessEvent(
            $this->createMock($authenticatorClass),
            new SelfValidatingPassport(new UserBadge('alice')),
            new NullToken(),
            $request,
            null,
            'netbs',
        ));

        return $request;
    }

    public function test_remember_me_login_does_not_record_an_authentication_time(): void
    {
        $request = $this->login(RememberMeAuthenticator::class);

        $this->assertFalse($request->getSession()->has(RecordAuthenticationTimeListener::SESSION_KEY));
    }

    public function test_password_login_records_the_authentication_time(): void
    {
        $request = $this->login(FormLoginAuthenticator::class);

        $this->assertEqualsWithDelta(time(), $request->getSession()->get(RecordAuthenticationTimeListener::SESSION_KEY), 2);
    }
}
