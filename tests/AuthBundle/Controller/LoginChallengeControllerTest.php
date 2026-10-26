<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Controller;

use NetBS\AuthBundle\Controller\Identity\LoginChallengeController;
use NetBS\AuthBundle\Listener\RecordAuthenticationTimeListener;
use NetBS\AuthBundle\Security\LocalSessionTerminator;
use NetBS\AuthBundle\Service\HydraAdminClient;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * Unit tests for {@see LoginChallengeController}. The controller's main job
 * is to mediate Hydra's `skip=true` shortcut safely — in particular, refusing
 * to re-bind a cached Hydra subject onto whichever user is currently sitting
 * in the Symfony session (the cross-account token-forgery defence at lines
 * 60-68 of the controller).
 */
final class LoginChallengeControllerTest extends TestCase
{
    /**
     * Build a HydraAdminClient backed by MockHttpClient. The list of $responses
     * is consumed in order by each HTTP call the controller issues. The
     * controller normally issues two: getLoginRequest, then either
     * acceptLoginRequest or rejectLoginRequest depending on subject-mismatch.
     *
     * @param list<MockResponse> $responses
     */
    private function makeHydra(array $responses, ?MockHttpClient &$mock = null): HydraAdminClient
    {
        $mock = new MockHttpClient($responses);
        return new HydraAdminClient($mock, new \Psr\Log\NullLogger());
    }

    private function jsonResponse(array $data): MockResponse
    {
        return new MockResponse(
            json_encode($data, JSON_THROW_ON_ERROR),
            ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']],
        );
    }

    /**
     * Plug a minimal container into the controller so AbstractController
     * helpers (`getUser`, `redirectToRoute`, `generateUrl`) work without
     * booting the kernel.
     *
     * @return array{controller: LoginChallengeController}
     */
    private function build(
        HydraAdminClient $hydra,
        ?string $currentSubject,
        bool $impersonated = false,
    ): array {
        $security = new class extends Security {
            public int $logoutCalls = 0;
            public function __construct() {}
            public function getUser(): ?\Symfony\Component\Security\Core\User\UserInterface
            {
                return null;
            }
            public function logout(bool $validateCsrfToken = true): ?Response
            {
                $this->logoutCalls++;
                return null;
            }
        };
        $controller = new LoginChallengeController($hydra, new LocalSessionTerminator($security), new \Psr\Log\NullLogger());

        $router = new class implements UrlGeneratorInterface {
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                return '/r/' . $name;
            }
            public function setContext(\Symfony\Component\Routing\RequestContext $context): void {}
            public function getContext(): \Symfony\Component\Routing\RequestContext
            {
                return new \Symfony\Component\Routing\RequestContext();
            }
        };

        $tokenStorage = new TokenStorage();
        if ($currentSubject !== null) {
            $user = new InMemoryUser($currentSubject, null);
            $token = $impersonated
                ? new SwitchUserToken($user, 'main', [], new UsernamePasswordToken(new InMemoryUser('admin', null), 'main'))
                : new UsernamePasswordToken($user, 'main');
            $tokenStorage->setToken($token);
        }

        $container = new class($router, $tokenStorage) implements ContainerInterface {
            public function __construct(
                public readonly UrlGeneratorInterface $router,
                public readonly TokenStorage $tokenStorage,
            ) {}
            public function get(string $id): mixed
            {
                return match ($id) {
                    'router' => $this->router,
                    'security.token_storage' => $this->tokenStorage,
                    default => throw new class("Service '$id' not stubbed") extends \RuntimeException implements NotFoundExceptionInterface {},
                };
            }
            public function has(string $id): bool
            {
                return in_array($id, ['router', 'security.token_storage'], true);
            }
        };
        $controller->setContainer($container);

        return ['controller' => $controller];
    }

    private function loginRequest(string $challenge = 'ch-1', array $sessionData = []): Request
    {
        $request = Request::create('/oidc-login', 'GET', ['login_challenge' => $challenge]);
        $session = new Session(new MockArraySessionStorage());
        foreach ($sessionData as $key => $value) {
            $session->set($key, $value);
        }
        $request->setSession($session);
        return $request;
    }

    private function loginRequestBody(string $query): array
    {
        return [
            'skip' => false,
            'client' => ['client_id' => 'acme'],
            'request_url' => 'https://auth.example/oauth2/auth?client_id=acme&' . $query,
        ];
    }

    // ---------------------------------------------------------------------

    public function testThrowsAccessDeniedOrEquivalentWhenNoUserOnSessionForSkipTrue(): void
    {
        // The firewall should have redirected to /login before we got here.
        // If somehow we still arrive with no authenticated user, refuse.
        $hydra = $this->makeHydra([
            // getLoginRequest response — must come first.
            $this->jsonResponse(['skip' => true, 'subject' => 'alice', 'client' => ['client_id' => 'acme']]),
        ]);
        $built = $this->build($hydra, currentSubject: null);
        /** @var LoginChallengeController $controller */
        $controller = $built['controller'];

        $this->expectException(AccessDeniedException::class);
        $controller($this->loginRequest());
    }

    public function testSkipTrueWithSubjectMismatchRejectsWithLoginRequired(): void
    {
        // Hydra has a cached session for `bob`, but locally `alice` is signed
        // in. Blindly accepting with `subject=alice` would silently rebind
        // Hydra's session to alice — a cross-account token-forgery vector.
        // The controller must call rejectLoginRequest with `login_required`.
        $hydra = $this->makeHydra([
            $this->jsonResponse([
                'skip' => true,
                'subject' => 'bob',
                'client' => ['client_id' => 'acme'],
            ]),
            $this->jsonResponse(['redirect_to' => 'https://hydra.example/reject-cb']),
        ]);
        $built = $this->build($hydra, currentSubject: 'alice');
        /** @var LoginChallengeController $controller */
        $controller = $built['controller'];

        $response = $controller($this->loginRequest());

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('https://hydra.example/reject-cb', $response->getTargetUrl());
    }

    public function testSkipTrueWithMatchingSubjectAcceptsWithoutPrompt(): void
    {
        // Hydra has a session for alice AND alice is logged in locally —
        // the happy fast-path: accept without prompting.
        $hydra = $this->makeHydra([
            $this->jsonResponse([
                'skip' => true,
                'subject' => 'alice',
                'client' => ['client_id' => 'acme'],
            ]),
            $this->jsonResponse(['redirect_to' => 'https://rp.example/cb']),
        ]);
        $built = $this->build($hydra, currentSubject: 'alice');
        /** @var LoginChallengeController $controller */
        $controller = $built['controller'];

        $response = $controller($this->loginRequest());

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('https://rp.example/cb', $response->getTargetUrl());
    }

    public function testSubjectMismatchRevokesStaleHydraSession(): void
    {
        $hydra = $this->makeHydra([
            $this->jsonResponse(['skip' => true, 'subject' => 'bob', 'session_id' => 'sid-1', 'client' => ['client_id' => 'acme']]),
            new MockResponse('', ['http_code' => 204]),
            $this->jsonResponse(['redirect_to' => 'https://hydra.example/reject-cb']),
        ], $mock);
        $controller = $this->build($hydra, currentSubject: 'alice')['controller'];

        $response = $controller($this->loginRequest());

        $this->assertSame('https://hydra.example/reject-cb', $response->getTargetUrl());
        $this->assertSame(3, $mock->getRequestsCount());
    }

    public function testImpersonatedSessionIsRejected(): void
    {
        $hydra = $this->makeHydra([
            $this->jsonResponse(['skip' => false, 'client' => ['client_id' => 'acme']]),
            $this->jsonResponse(['redirect_to' => 'https://hydra.example/reject-cb']),
        ], $mock);
        $controller = $this->build($hydra, currentSubject: 'alice', impersonated: true)['controller'];

        $response = $controller($this->loginRequest());

        $this->assertSame('https://hydra.example/reject-cb', $response->getTargetUrl());
    }

    public function testPromptLoginForcesReauthentication(): void
    {
        $hydra = $this->makeHydra([
            $this->jsonResponse($this->loginRequestBody('prompt=login')),
        ], $mock);
        $controller = $this->build($hydra, currentSubject: 'alice')['controller'];
        $request = $this->loginRequest(sessionData: [RecordAuthenticationTimeListener::SESSION_KEY => time()]);

        $response = $controller($request);

        $this->assertSame('/r/netbs.secure.login.login', $response->getTargetUrl());
        $this->assertSame(1, $mock->getRequestsCount(), 'the login must not be accepted');
        $session = $request->getSession();
        $this->assertSame($request->getUri(), $session->get('_security.oidc.target_path'));
        $this->assertIsInt($session->get('_oidc.reauth_requested_at.ch-1'));
    }

    public function testPromptLoginIsSatisfiedAfterFreshLogin(): void
    {
        $hydra = $this->makeHydra([
            $this->jsonResponse($this->loginRequestBody('prompt=login')),
            $this->jsonResponse(['redirect_to' => 'https://rp.example/cb']),
        ]);
        $controller = $this->build($hydra, currentSubject: 'alice')['controller'];
        $now = time();

        $response = $controller($this->loginRequest(sessionData: [
            '_oidc.reauth_requested_at.ch-1' => $now - 5,
            RecordAuthenticationTimeListener::SESSION_KEY => $now,
        ]));

        $this->assertSame('https://rp.example/cb', $response->getTargetUrl());
    }

    public function testMaxAgeExceededForcesReauthentication(): void
    {
        $hydra = $this->makeHydra([
            $this->jsonResponse($this->loginRequestBody('max_age=60')),
        ]);
        $controller = $this->build($hydra, currentSubject: 'alice')['controller'];

        $response = $controller($this->loginRequest(sessionData: [
            RecordAuthenticationTimeListener::SESSION_KEY => time() - 3600,
        ]));

        $this->assertSame('/r/netbs.secure.login.login', $response->getTargetUrl());
    }

    public function testReauthWithPromptNoneRejectsLoginRequired(): void
    {
        $hydra = $this->makeHydra([
            $this->jsonResponse($this->loginRequestBody('prompt=none&max_age=0')),
            $this->jsonResponse(['redirect_to' => 'https://hydra.example/reject-cb']),
        ]);
        $controller = $this->build($hydra, currentSubject: 'alice')['controller'];

        $response = $controller($this->loginRequest(sessionData: [
            RecordAuthenticationTimeListener::SESSION_KEY => time() - 10,
        ]));

        $this->assertSame('https://hydra.example/reject-cb', $response->getTargetUrl());
    }

    public function testSubjectSentToHydraIsLowercased(): void
    {
        // lldap stores uids lowercase, so the OIDC subject must be too.
        $acceptBody = null;
        $hydra = $this->makeHydra([
            $this->jsonResponse(['skip' => false, 'client' => ['client_id' => 'acme']]),
            function (string $method, string $url, array $options) use (&$acceptBody) {
                $acceptBody = json_decode($options['body'], true);
                return $this->jsonResponse(['redirect_to' => 'https://rp.example/cb']);
            },
        ]);
        $controller = $this->build($hydra, currentSubject: 'Equipe')['controller'];

        $controller($this->loginRequest());

        $this->assertSame('equipe', $acceptBody['subject'] ?? null);
    }

    public function testExpiredChallengeLandsOnTheDashboard(): void
    {
        // A target path left by an abandoned RP sign-in, replayed after a
        // later direct netBS login: Hydra no longer knows the challenge.
        $hydra = $this->makeHydra([new MockResponse('{"error":"not_found"}', ['http_code' => 404])]);
        $controller = $this->build($hydra, currentSubject: 'alice')['controller'];

        $response = $controller($this->loginRequest());

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/r/netbs.core.home.dashboard', $response->getTargetUrl());
    }
}
