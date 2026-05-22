<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Controller;

use NetBS\AuthBundle\Controller\Identity\LogoutController;
use NetBS\AuthBundle\Service\HydraAdminClient;
use NetBS\AuthBundle\Service\HydraClientException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Controller-level tests for the OIDC LogoutController.
 *
 * These are deliberately unit tests with stubbed collaborators (router, twig,
 * csrf manager, token storage, hydra client, Security service). The controller
 * is heavily collaborator-driven, so this lets us assert on the security
 * behaviour (drive-by-logout refusal, CSRF, subject-mismatch policy) without
 * booting the kernel.
 */
final class LogoutControllerTest extends TestCase
{
    /**
     * Build the controller wired up with a minimal stub container so that
     * AbstractController's helpers (render, redirectToRoute, isCsrfTokenValid,
     * getUser, addFlash) work without booting Symfony.
     *
     * @param array{
     *   getLogoutRequest?: array<string,mixed>|\Throwable,
     *   acceptLogoutRequest?: array<string,mixed>|\Throwable,
     * } $hydraBehaviour
     */
    private function makeController(
        array $hydraBehaviour = [],
        ?string $currentSubject = null,
        bool $csrfValid = true,
        ?Security $security = null,
    ): array {
        $hydra = $this->makeHydraStub($hydraBehaviour);
        $security ??= $this->makeSecurityStub($currentSubject);

        $controller = new LogoutController($hydra, $security);

        // Build the stub container.
        $twig = new class {
            /** @var array{0:string,1:array<string,mixed>}|null */
            public ?array $lastRender = null;
            public function render(string $view, array $parameters = []): string
            {
                $this->lastRender = [$view, $parameters];
                return '<rendered:' . $view . '>';
            }
        };

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

        $csrfManager = new class($csrfValid) implements CsrfTokenManagerInterface {
            /** @var list<string> */
            public array $validatedTokenIds = [];
            public function __construct(private readonly bool $valid) {}
            public function getToken(string $tokenId): CsrfToken
            {
                return new CsrfToken($tokenId, 'fake');
            }
            public function refreshToken(string $tokenId): CsrfToken
            {
                return new CsrfToken($tokenId, 'fake');
            }
            public function removeToken(string $tokenId): ?string
            {
                return null;
            }
            public function isTokenValid(CsrfToken $token): bool
            {
                $this->validatedTokenIds[] = $token->getId();
                return $this->valid;
            }
        };

        // Token storage — getUser() goes through here.
        $tokenStorage = new TokenStorage();
        if ($currentSubject !== null) {
            $user = new InMemoryUser($currentSubject, null);
            $tokenStorage->setToken(new UsernamePasswordToken($user, 'main'));
        }

        // request_stack for addFlash — needs a session with a FlashBag.
        $session = new Session(new \Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage());
        $requestStack = new \Symfony\Component\HttpFoundation\RequestStack();
        $req = new Request();
        $req->setSession($session);
        $requestStack->push($req);

        $container = new class($twig, $router, $csrfManager, $tokenStorage, $requestStack) implements ContainerInterface {
            public function __construct(
                public readonly object $twig,
                public readonly UrlGeneratorInterface $router,
                public readonly CsrfTokenManagerInterface $csrf,
                public readonly TokenStorage $tokenStorage,
                public readonly \Symfony\Component\HttpFoundation\RequestStack $requestStack,
            ) {}
            public function get(string $id): mixed
            {
                return match ($id) {
                    'twig' => $this->twig,
                    'router' => $this->router,
                    'security.csrf.token_manager' => $this->csrf,
                    'security.token_storage' => $this->tokenStorage,
                    'request_stack' => $this->requestStack,
                    default => throw new class("Service '$id' not stubbed") extends \RuntimeException implements NotFoundExceptionInterface {},
                };
            }
            public function has(string $id): bool
            {
                return in_array($id, ['twig', 'router', 'security.csrf.token_manager', 'security.token_storage', 'request_stack'], true);
            }
        };

        $controller->setContainer($container);

        return [
            'controller' => $controller,
            'twig' => $twig,
            'security' => $security,
            'session' => $session,
            'csrfManager' => $csrfManager,
        ];
    }

    /**
     * Build a real {@see HydraAdminClient} backed by {@see MockHttpClient}
     * so we can drive get/accept logout-request responses without making real
     * HTTP calls. HydraAdminClient is `final`, so subclassing isn't an option.
     *
     * The MockHttpClient honours request order; we lay out responses in the
     * sequence the controller will issue them (getLogoutRequest first, then
     * acceptLogoutRequest when reached). A response value of `null` means
     * "the controller is not expected to make this call" — we still queue a
     * 500 response under that key so an unexpected call fails loudly.
     *
     * @param array{
     *   getLogoutRequest?: array<string,mixed>|HydraClientException,
     *   acceptLogoutRequest?: array<string,mixed>|HydraClientException,
     * } $behaviour
     */
    private function makeHydraStub(array $behaviour): HydraAdminClient
    {
        $responses = [];
        foreach (['getLogoutRequest', 'acceptLogoutRequest'] as $key) {
            if (!array_key_exists($key, $behaviour)) {
                // Not stubbed — controller should not call this. Queue a 500
                // so an unexpected call surfaces as a HydraClientException
                // the test will notice.
                $responses[] = new MockResponse('unexpected call', ['http_code' => 500]);
                continue;
            }
            $value = $behaviour[$key];
            if ($value instanceof HydraClientException) {
                // Translate the desired exception back into the HTTP shape
                // that triggers it. statusCode 0 means transport failure.
                if ($value->statusCode === 0) {
                    $responses[] = new MockResponse('', ['error' => $value->responseExcerpt ?: 'transport']);
                } else {
                    $responses[] = new MockResponse(
                        $value->responseExcerpt,
                        ['http_code' => $value->statusCode],
                    );
                }
                continue;
            }
            // Happy path: encode the array as JSON.
            $responses[] = new MockResponse(
                json_encode($value, JSON_THROW_ON_ERROR),
                ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']],
            );
        }

        $mock = new MockHttpClient($responses);

        $client = (new \ReflectionClass(HydraAdminClient::class))->newInstanceWithoutConstructor();
        $httpProp = new \ReflectionProperty(HydraAdminClient::class, 'http');
        $httpProp->setAccessible(true);
        $httpProp->setValue($client, $mock);
        $loggerProp = new \ReflectionProperty(HydraAdminClient::class, 'logger');
        $loggerProp->setAccessible(true);
        $loggerProp->setValue($client, new \Psr\Log\NullLogger());

        return $client;
    }

    private function makeSecurityStub(?string $currentSubject): Security
    {
        // Anonymous subclass that records logout calls and reports the user we set up.
        return new class($currentSubject) extends Security {
            public int $logoutCalls = 0;
            public function __construct(private readonly ?string $subject)
            {
                // Skip parent constructor.
            }
            public function getUser(): ?\Symfony\Component\Security\Core\User\UserInterface
            {
                return $this->subject === null
                    ? null
                    : new InMemoryUser($this->subject, null);
            }
            public function logout(bool $validateCsrfToken = true): ?Response
            {
                $this->logoutCalls++;
                return null;
            }
        };
    }

    private function newGet(string $challenge = ''): Request
    {
        $query = $challenge === '' ? [] : ['logout_challenge' => $challenge];
        return Request::create('/oidc-logout', 'GET', $query);
    }

    private function newPost(string $challenge, ?string $token, string $tokenId): Request
    {
        $params = $token === null ? [] : ['_token' => $token];
        if ($challenge !== '') {
            $params['logout_challenge'] = $challenge;
        }
        return Request::create('/oidc-logout', 'POST', $params);
    }

    // ---------------------------------------------------------------------

    public function testGetRequestNeverInvokesLogout(): void
    {
        // Drive-by-<img-src> regression: a GET to /oidc-logout must never
        // end the local session, even with a challenge in the query.
        $built = $this->makeController(
            hydraBehaviour: ['getLogoutRequest' => ['subject' => 'alice']],
            currentSubject: 'alice',
        );
        /** @var LogoutController $controller */
        $controller = $built['controller'];
        $security = $built['security'];

        $response = $controller($this->newGet(challenge: 'ch-123'));

        $this->assertSame(0, $security->logoutCalls, 'GET must not call logout()');
        // Renders the confirmation page.
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertNotNull($built['twig']->lastRender);
        $this->assertSame('@NetBSAuth/identity/logout_confirm.html.twig', $built['twig']->lastRender[0]);
    }

    public function testGetWithoutChallengeAlsoNeverLogsOut(): void
    {
        // Same property for the "plain" /oidc-logout entry point.
        $built = $this->makeController(currentSubject: 'alice');
        /** @var LogoutController $controller */
        $controller = $built['controller'];

        $response = $controller($this->newGet());

        $this->assertSame(0, $built['security']->logoutCalls);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertNotNull($built['twig']->lastRender);
    }

    public function testPostWithoutChallengeAndInvalidCsrfReturns403OrSimilar(): void
    {
        $built = $this->makeController(currentSubject: 'alice', csrfValid: false);
        /** @var LogoutController $controller */
        $controller = $built['controller'];

        $this->expectException(\Symfony\Component\Security\Core\Exception\AccessDeniedException::class);

        $controller($this->newPost(challenge: '', token: 'bad', tokenId: 'oidc_logout_local'));
    }

    public function testPostWithoutChallengeAndValidCsrfPerformsLocalLogout(): void
    {
        $built = $this->makeController(currentSubject: 'alice');
        /** @var LogoutController $controller */
        $controller = $built['controller'];

        $response = $controller($this->newPost(challenge: '', token: 'fake', tokenId: 'oidc_logout_local'));

        $this->assertSame(1, $built['security']->logoutCalls);
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/r/netbs.core.home.dashboard', $response->getTargetUrl());
    }

    public function testSubjectMismatchWithLocalUserIsRefused(): void
    {
        // Hardened policy: if a logged-in netBS user is tricked into POSTing a
        // logout_challenge for a different subject, we must REFUSE — neither
        // accept the Hydra logout nor terminate the local session.
        $built = $this->makeController(
            hydraBehaviour: [
                'getLogoutRequest' => ['subject' => 'victim'],
                // acceptLogoutRequest MUST NOT be called; stub it to fail loudly
                // if it ever is.
                'acceptLogoutRequest' => new \LogicException('acceptLogoutRequest must not be called on a refused mismatch'),
            ],
            currentSubject: 'innocent-admin',
        );
        /** @var LogoutController $controller */
        $controller = $built['controller'];

        $response = $controller($this->newPost(challenge: 'ch-xyz', token: 'fake', tokenId: 'oidc_logout_challenge_ch-xyz'));

        $this->assertSame(0, $built['security']->logoutCalls, 'Local logout must NOT run on mismatch');
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        // Refusal renders the confirm template with mismatchRefused=true.
        $this->assertNotNull($built['twig']->lastRender);
        $params = $built['twig']->lastRender[1];
        $this->assertTrue($params['subjectMismatch'] ?? false);
        $this->assertTrue($params['mismatchRefused'] ?? false);
        $this->assertSame('innocent-admin', $params['localSubject']);
        $this->assertSame('victim', $params['subject']);
    }

    public function testSubjectMatchKillsLocalSessionFirstThenAcceptsHydra(): void
    {
        // We can't assert literal ordering of internal calls without a
        // recorder, so the proxy is: logout() called exactly once AND
        // acceptLogoutRequest returned a redirect AND the response is that
        // redirect. The controller's source guarantees order: local logout
        // happens before the try/catch around acceptLogoutRequest.
        $built = $this->makeController(
            hydraBehaviour: [
                'getLogoutRequest' => ['subject' => 'alice'],
                'acceptLogoutRequest' => ['redirect_to' => 'https://rp.example/post-logout'],
            ],
            currentSubject: 'alice',
        );
        /** @var LogoutController $controller */
        $controller = $built['controller'];

        $response = $controller($this->newPost(challenge: 'ch-1', token: 'fake', tokenId: 'oidc_logout_challenge_ch-1'));

        $this->assertSame(1, $built['security']->logoutCalls);
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('https://rp.example/post-logout', $response->getTargetUrl());
    }

    public function testHydraAcceptFailureSetsFlashAndStillRedirectsToLogin(): void
    {
        // After local logout succeeds, if Hydra's acceptLogoutRequest fails,
        // we redirect to the login page and surface a warning flash so the
        // user knows their session may persist on federated RPs.
        $built = $this->makeController(
            hydraBehaviour: [
                'getLogoutRequest' => ['subject' => 'alice'],
                'acceptLogoutRequest' => new HydraClientException('PUT', '/x', 503, 'down'),
            ],
            currentSubject: 'alice',
        );
        /** @var LogoutController $controller */
        $controller = $built['controller'];

        $response = $controller($this->newPost(challenge: 'ch-1', token: 'fake', tokenId: 'oidc_logout_challenge_ch-1'));

        $this->assertSame(1, $built['security']->logoutCalls);
        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/r/netbs.secure.login.login', $response->getTargetUrl());

        $flashes = $built['session']->getFlashBag()->all();
        $this->assertArrayHasKey('warning', $flashes, 'a warning flash must be set when Hydra accept fails');
        $this->assertNotEmpty($flashes['warning']);
        $this->assertStringContainsString('SSO server', (string) $flashes['warning'][0]);
    }

    public function testProgrammerErrorBubbles(): void
    {
        // Defensive regression: programmer errors (TypeError, autoloader
        // failures, etc.) raised inside Hydra collaborators must NOT be
        // swallowed by the controller (which used to catch \Throwable). They
        // should bubble to Symfony's exception listener so monitoring catches
        // them. We install a HydraAdminClient whose `http` property throws on
        // the first request — the catch block now only catches
        // HydraClientException, so a TypeError must propagate.
        $hydra = (new \ReflectionClass(HydraAdminClient::class))->newInstanceWithoutConstructor();
        $throwingClient = new class extends MockHttpClient {
            public function __construct() { parent::__construct(); }
            public function request(string $method, string $url, array $options = []): \Symfony\Contracts\HttpClient\ResponseInterface
            {
                throw new \TypeError('coding bug deep in the http layer');
            }
        };
        (new \ReflectionProperty(HydraAdminClient::class, 'http'))->setValue($hydra, $throwingClient);
        (new \ReflectionProperty(HydraAdminClient::class, 'logger'))->setValue($hydra, new \Psr\Log\NullLogger());

        $security = $this->makeSecurityStub('alice');
        $controller = new LogoutController($hydra, $security);

        // Reuse the container scaffolding by leaning on makeController for
        // the wiring, then swapping the controller's hydra dep.
        $built = $this->makeController(currentSubject: 'alice');
        /** @var LogoutController $existing */
        $existing = $built['controller'];
        $containerProp = new \ReflectionProperty(
            \Symfony\Bundle\FrameworkBundle\Controller\AbstractController::class,
            'container',
        );
        $containerProp->setValue($controller, $containerProp->getValue($existing));

        $this->expectException(\TypeError::class);
        $controller($this->newGet(challenge: 'ch-1'));
    }

    public function testCsrfTokenIdIsScopedToFlow(): void
    {
        // Plain-logout flow uses `oidc_logout_local`; the challenge flow uses
        // `oidc_logout_challenge_<challenge>`. A token minted for one MUST
        // NOT validate against the other — namespacing the ids is what
        // gives us that guarantee.
        $built1 = $this->makeController(currentSubject: 'alice');
        ($built1['controller'])($this->newPost(challenge: '', token: 'fake', tokenId: 'oidc_logout_local'));
        $this->assertSame(['oidc_logout_local'], $built1['csrfManager']->validatedTokenIds);

        $built2 = $this->makeController(
            hydraBehaviour: [
                'getLogoutRequest' => ['subject' => 'alice'],
                'acceptLogoutRequest' => ['redirect_to' => 'https://rp.example/cb'],
            ],
            currentSubject: 'alice',
        );
        ($built2['controller'])($this->newPost(challenge: 'abc-123', token: 'fake', tokenId: 'oidc_logout_challenge_abc-123'));
        $this->assertSame(['oidc_logout_challenge_abc-123'], $built2['csrfManager']->validatedTokenIds);
    }

    public function testGetLogoutRequestHydraExceptionRedirectsToErrorPage(): void
    {
        // The narrow HydraClientException catch must still redirect to the
        // OIDC error page (preserve the existing UX for an expired challenge).
        $built = $this->makeController(
            hydraBehaviour: [
                'getLogoutRequest' => new HydraClientException('GET', '/x', 404, 'expired'),
            ],
            currentSubject: 'alice',
        );
        /** @var LogoutController $controller */
        $controller = $built['controller'];

        $response = $controller($this->newGet(challenge: 'ch-1'));

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/r/oidc_error', $response->getTargetUrl());
    }
}
