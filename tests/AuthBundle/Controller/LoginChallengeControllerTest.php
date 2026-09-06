<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Controller;

use NetBS\AuthBundle\Controller\Identity\LoginChallengeController;
use NetBS\AuthBundle\Service\HydraAdminClient;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
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
    private function makeHydra(array $responses): HydraAdminClient
    {
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
    ): array {
        $controller = new LoginChallengeController($hydra);

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
            $tokenStorage->setToken(new UsernamePasswordToken($user, 'main'));
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

    private function loginRequest(string $challenge = 'ch-1'): Request
    {
        return Request::create('/oidc-login', 'GET', ['login_challenge' => $challenge]);
    }

    // ---------------------------------------------------------------------

    public function testRejectsWhenChallengeMissingFromQuery(): void
    {
        $hydra = $this->makeHydra([]);
        $built = $this->build($hydra, currentSubject: 'alice');
        /** @var LoginChallengeController $controller */
        $controller = $built['controller'];

        $this->expectException(\InvalidArgumentException::class);
        $controller(Request::create('/oidc-login', 'GET'));
    }

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

    public function testSkipFalseAcceptsWithCurrentSession(): void
    {
        // When Hydra doesn't request skip, the controller still treats a
        // present Symfony session as proof of authentication and accepts
        // the login request with the local user as subject. (The non-skip
        // behaviour is "accept with the authenticated user" — this is what
        // the firewall would have arranged before routing here.)
        $hydra = $this->makeHydra([
            $this->jsonResponse([
                'skip' => false,
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

    public function testSkipTrueWithMissingHydraSubjectRejects(): void
    {
        // If skip=true but Hydra didn't return a subject string, that's an
        // odd state — the controller's hash_equals guard rejects (we can't
        // verify the subject match without it).
        $hydra = $this->makeHydra([
            $this->jsonResponse([
                'skip' => true,
                // no `subject` field
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
}
