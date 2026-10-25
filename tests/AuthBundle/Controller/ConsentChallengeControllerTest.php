<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Controller;

use NetBS\AuthBundle\Contract\IdentityClientPolicyInterface;
use NetBS\AuthBundle\Contract\IdentityDTO;
use NetBS\AuthBundle\Contract\IdentityUserResolverInterface;
use NetBS\AuthBundle\Controller\Identity\ConsentChallengeController;
use NetBS\AuthBundle\Service\ClaimsAssembler;
use NetBS\AuthBundle\Service\HydraAdminClient;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

/**
 * A denied user must land on netBS's own error page (naming the app), not be
 * bounced back to the client with a bare `error=access_denied` it can only
 * show as a generic failure.
 */
final class ConsentChallengeControllerTest extends TestCase
{
    public function testPolicyDenialRendersTheErrorPageNamingTheClient(): void
    {
        $mock = new MockHttpClient([
            $this->json(self::consentRequest()),
            $this->json(['redirect_to' => 'http://wiki.localhost:8080/cb?error=access_denied']),
        ]);

        $response = $this->controller($mock, $this->identity(), allowed: false)($this->request());

        $this->assertNotInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertStringContainsString("pas accès à Wiki BS.", $response->getContent());
        // The challenge is still rejected in Hydra so it can't be replayed.
        $this->assertSame(2, $mock->getRequestsCount());
    }

    public function testDisabledUserRendersTheErrorPage(): void
    {
        $mock = new MockHttpClient([
            $this->json(self::consentRequest()),
            $this->json(['redirect_to' => 'http://wiki.localhost:8080/cb?error=access_denied']),
        ]);

        $response = $this->controller($mock, $this->identity(disabled: true), allowed: true)($this->request());

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertStringContainsString("pas accès à Wiki BS.", $response->getContent());
    }

    public function testErrorPageIsShownEvenWhenHydraRejectFails(): void
    {
        $mock = new MockHttpClient([
            $this->json(self::consentRequest()),
            new MockResponse('', ['http_code' => 500]),
        ]);

        $response = $this->controller($mock, $this->identity(), allowed: false)($this->request());

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testAllowedUserIsRedirectedBackToTheClient(): void
    {
        $mock = new MockHttpClient([
            $this->json(self::consentRequest()),
            $this->json(['redirect_to' => 'http://wiki.localhost:8080/cb?code=abc']),
        ]);

        $response = $this->controller($mock, $this->identity(), allowed: true)($this->request());

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('http://wiki.localhost:8080/cb?code=abc', $response->getTargetUrl());
    }

    private static function consentRequest(): array
    {
        return [
            'subject' => 'alice',
            'client' => ['client_id' => 'client-wiki', 'client_name' => 'Wiki BS'],
            'requested_scope' => ['openid'],
        ];
    }

    private function controller(MockHttpClient $http, IdentityDTO $identity, bool $allowed): ConsentChallengeController
    {
        $resolver = $this->createMock(IdentityUserResolverInterface::class);
        $resolver->method('resolveBySub')->willReturn($identity);
        $policy = $this->createMock(IdentityClientPolicyInterface::class);
        $policy->method('canAccess')->willReturn($allowed);

        $controller = new ConsentChallengeController(
            new HydraAdminClient($http, new \Psr\Log\NullLogger()),
            $resolver,
            $policy,
            new ClaimsAssembler(),
            new \Psr\Log\NullLogger(),
        );

        $twig = new Environment(new ArrayLoader([
            '@NetBSAuth/identity/error.html.twig' => '{{ userMessage }}',
        ]));
        $controller->setContainer(new class($twig) implements ContainerInterface {
            public function __construct(private readonly Environment $twig) {}
            public function get(string $id): mixed
            {
                return $id === 'twig'
                    ? $this->twig
                    : throw new class("Service '$id' not stubbed") extends \RuntimeException implements NotFoundExceptionInterface {};
            }
            public function has(string $id): bool
            {
                return $id === 'twig';
            }
        });

        return $controller;
    }

    private function identity(bool $disabled = false): IdentityDTO
    {
        return new IdentityDTO(
            sub: 'alice',
            preferredUsername: 'alice',
            email: null,
            displayName: 'Alice',
            isDisabled: $disabled,
        );
    }

    private function request(): Request
    {
        return Request::create('/oidc-consent', 'GET', ['consent_challenge' => 'ch-1']);
    }

    private function json(array $data): MockResponse
    {
        return new MockResponse(
            json_encode($data, JSON_THROW_ON_ERROR),
            ['http_code' => 200, 'response_headers' => ['content-type' => 'application/json']],
        );
    }
}
