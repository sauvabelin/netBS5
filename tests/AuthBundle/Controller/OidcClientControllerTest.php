<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Controller;

use NetBS\AuthBundle\Controller\Admin\OidcClientController;
use NetBS\AuthBundle\Service\HydraAdminClient;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Twig\Environment;

/**
 * Unit-level coverage for the Hydra-backed OAuth admin controller.
 *
 * The bundle does not ship a kernel-level test bootstrap (no application
 * services are wired in `tests/bootstrap.php`), so a {@see \Symfony\Bundle\FrameworkBundle\Test\WebTestCase}
 * end-to-end harness is not viable here. Instead we exercise each action
 * directly against a PSR container that exposes the same service ids
 * AbstractController subscribes to, with hand-rolled fakes for the parts
 * we actually touch:
 *
 *   - `security.authorization_checker`  → toggles 403 admin guard
 *   - `security.csrf.token_manager`     → toggles CSRF validation
 *   - `request_stack`                   → real RequestStack with a real
 *                                         Session so addFlash() lands in
 *                                         a flashbag we can assert on
 *   - `router`                          → tiny stub for redirectToRoute()
 *   - `twig`                            → captures view name / parameters
 *
 * {@see HydraAdminClient} is `final`, so PHPUnit cannot double it. We
 * reuse the test pattern from {@see \App\Tests\AuthBundle\Service\HydraAdminClientTest}:
 * instantiate the real client via reflection (no ctor) and back it with
 * a {@see MockHttpClient} so we can shape every Hydra response.
 */
final class OidcClientControllerTest extends TestCase
{
    private HydraAdminClient $hydra;
    private MockHttpClient $http;
    private Session $session;
    private bool $isAdmin = true;
    private bool $csrfValid = true;
    /** @var array{view: ?string, parameters: array<string,mixed>} */
    private array $rendered = ['view' => null, 'parameters' => []];

    protected function setUp(): void
    {
        // Default to an empty response queue; tests append whatever they need.
        $this->http = new MockHttpClient([]);
        $this->hydra = $this->buildHydra($this->http);
        $this->session = new Session(new MockArraySessionStorage());
        $this->isAdmin = true;
        $this->csrfValid = true;
        $this->rendered = ['view' => null, 'parameters' => []];
    }

    private function buildHydra(MockHttpClient $mock): HydraAdminClient
    {
        $client = (new \ReflectionClass(HydraAdminClient::class))
            ->newInstanceWithoutConstructor();

        $httpProp = new \ReflectionProperty(HydraAdminClient::class, 'http');
        $httpProp->setAccessible(true);
        $httpProp->setValue($client, $mock);

        $loggerProp = new \ReflectionProperty(HydraAdminClient::class, 'logger');
        $loggerProp->setAccessible(true);
        $loggerProp->setValue($client, new NullLogger());

        return $client;
    }

    private function makeController(): OidcClientController
    {
        $controller = new OidcClientController($this->hydra, new NullLogger());

        $isAdmin = &$this->isAdmin;
        $auth = new class($isAdmin) implements AuthorizationCheckerInterface {
            public function __construct(private bool &$isAdmin) {}
            public function isGranted(mixed $attribute, mixed $subject = null): bool
            {
                return $this->isAdmin;
            }
        };

        $csrfValid = &$this->csrfValid;
        $csrf = new class($csrfValid) implements CsrfTokenManagerInterface {
            public function __construct(private bool &$valid) {}
            public function getToken(string $tokenId): CsrfToken
            {
                return new CsrfToken($tokenId, 'token');
            }
            public function refreshToken(string $tokenId): CsrfToken
            {
                return $this->getToken($tokenId);
            }
            public function removeToken(string $tokenId): ?string { return null; }
            public function isTokenValid(CsrfToken $token): bool
            {
                return $this->valid;
            }
        };

        $router = new class implements UrlGeneratorInterface {
            public function generate(string $name, array $parameters = [], int $referenceType = self::ABSOLUTE_PATH): string
            {
                $qs = $parameters === [] ? '' : '?' . http_build_query($parameters);
                return '/_route/' . $name . $qs;
            }
            public function setContext(\Symfony\Component\Routing\RequestContext $context): void {}
            public function getContext(): \Symfony\Component\Routing\RequestContext
            {
                return new \Symfony\Component\Routing\RequestContext();
            }
        };

        $session = $this->session;
        $requestStack = new RequestStack();
        $request = new Request();
        $request->setSession($session);
        $requestStack->push($request);

        // Twig captures so we can assert on what the controller asked to render.
        $rendered = &$this->rendered;
        $twig = $this->getMockBuilder(Environment::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['render'])
            ->getMock();
        $twig->method('render')->willReturnCallback(static function (string $view, array $parameters = []) use (&$rendered): string {
            $rendered['view'] = $view;
            $rendered['parameters'] = $parameters;
            return '<html><!-- ' . $view . ' --></html>';
        });

        $container = new class($auth, $csrf, $router, $requestStack, $twig) implements ContainerInterface {
            /** @var array<string, object> */
            private array $services;
            public function __construct(
                AuthorizationCheckerInterface $auth,
                CsrfTokenManagerInterface $csrf,
                UrlGeneratorInterface $router,
                RequestStack $requestStack,
                Environment $twig,
            ) {
                $this->services = [
                    'security.authorization_checker' => $auth,
                    'security.csrf.token_manager' => $csrf,
                    'router' => $router,
                    'request_stack' => $requestStack,
                    'twig' => $twig,
                ];
            }
            public function has(string $id): bool { return isset($this->services[$id]); }
            public function get(string $id): object
            {
                if (!isset($this->services[$id])) {
                    throw new \RuntimeException("No service '$id' configured in test container.");
                }
                return $this->services[$id];
            }
        };

        $controller->setContainer($container);

        return $controller;
    }

    public function testIndexAsNonAdminIsForbidden(): void
    {
        $this->isAdmin = false;
        $controller = $this->makeController();

        // Hydra must not even be queried when ROLE_ADMIN is missing — so we
        // intentionally leave the HTTP mock empty: any outbound request
        // would blow up with "no responses left".
        $this->expectException(AccessDeniedException::class);
        $controller->index();
    }

    public function testCreatePostWithoutCsrfTokenIsRejected(): void
    {
        $this->csrfValid = false;
        $controller = $this->makeController();

        $this->expectException(AccessDeniedException::class);
        $controller->create(new Request());
    }

    public function testDeletePostWithoutCsrfTokenIsRejected(): void
    {
        $this->csrfValid = false;
        $controller = $this->makeController();

        $this->expectException(AccessDeniedException::class);
        $controller->delete('client-abc', new Request());
    }

    public function testRegenerateSecretPostWithoutCsrfTokenIsRejected(): void
    {
        $this->csrfValid = false;
        $controller = $this->makeController();

        $this->expectException(AccessDeniedException::class);
        $controller->regenerateSecret('client-abc', new Request());
    }

    public function testListDegradesGracefullyWhenHydraFails(): void
    {
        // Simulate a Hydra outage during the listing call. The controller is
        // contracted to catch HydraClientException, log it, surface a generic
        // flash, and still render the list page with an empty collection —
        // *not* bubble up as a 500.
        $this->http = new MockHttpClient([
            new MockResponse('service unavailable', ['http_code' => 503]),
        ]);
        $this->hydra = $this->buildHydra($this->http);

        $controller = $this->makeController();
        $response = $controller->index();

        // Page still renders — no exception bubbled to the kernel.
        $this->assertInstanceOf(Response::class, $response);
        $this->assertNotInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $this->assertSame('@NetBSAuth/admin/oidc/list.html.twig', $this->rendered['view']);
        $this->assertSame([], $this->rendered['parameters']['clients']);

        // Flash bag holds a friendly error — and crucially does *not* leak
        // the internal Hydra URL / status excerpt that lived on the exception.
        $errors = $this->session->getFlashBag()->get('error');
        $this->assertCount(1, $errors);
        $this->assertStringNotContainsString('/admin/clients', $errors[0]);
        $this->assertStringNotContainsString('service unavailable', $errors[0]);
        $this->assertStringNotContainsString('503', $errors[0]);
    }
}
