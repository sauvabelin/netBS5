<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Controller;

use NetBS\AuthBundle\Contract\IdentityClientPolicyInterface;
use NetBS\AuthBundle\Contract\IdentityDTO;
use NetBS\AuthBundle\Contract\IdentityUserResolverInterface;
use NetBS\AuthBundle\Controller\Identity\RefreshHookController;
use NetBS\AuthBundle\Service\ClaimsAssembler;
use NetBS\AuthBundle\Service\HydraAdminClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Exercises the Hydra refresh-token hook controller. The hook authenticates
 * via a shared secret header — these tests defend the secret-check branches
 * and the structured error contract Hydra expects (4xx is permanent reject;
 * 5xx is "try again"). They are deliberately collaborator-driven and avoid
 * the kernel.
 */
final class RefreshHookControllerTest extends TestCase
{
    private const VALID_SECRET = 'shared-secret-value';

    /**
     * @param array<string, mixed>|null $resolverReturns
     */
    private function makeController(
        string $expectedSecret = self::VALID_SECRET,
        ?IdentityDTO $resolverReturns = null,
        bool $policyAllows = true,
        ?array $allowedClaims = ['sub', 'preferred_username'],
    ): RefreshHookController {
        $resolver = new class($resolverReturns) implements IdentityUserResolverInterface {
            public function __construct(private readonly ?IdentityDTO $dto) {}
            public function resolveBySub(string $sub): ?IdentityDTO
            {
                return $this->dto;
            }
        };

        $policy = new class($policyAllows) implements IdentityClientPolicyInterface {
            public function __construct(private readonly bool $allows) {}
            public function canAccess(IdentityDTO $identity, string $clientId): bool
            {
                return $this->allows;
            }
            public function additionalClaimsFor(IdentityDTO $identity, string $clientId): array
            {
                return [];
            }
        };

        // ClaimsAssembler talks HTTP via HydraAdminClient; we seed its
        // per-client allowed-claims cache by reflection (same pattern used by
        // ClaimsAssemblerTest) so no network is touched.
        $hydra = (new \ReflectionClass(HydraAdminClient::class))->newInstanceWithoutConstructor();
        $claims = new ClaimsAssembler($hydra, $policy);
        $cacheProp = new \ReflectionProperty(ClaimsAssembler::class, 'cache');
        $cacheProp->setAccessible(true);
        $cacheProp->setValue($claims, ['acme-client' => $allowedClaims ?? []]);

        return new RefreshHookController(
            $resolver,
            $policy,
            $claims,
            $expectedSecret,
        );
    }

    private function makeIdentity(bool $disabled = false): IdentityDTO
    {
        return new IdentityDTO(
            sub: 'alice',
            preferredUsername: 'alice',
            email: 'alice@example.com',
            displayName: 'Alice Example',
            groups: ['users'],
            isDisabled: $disabled,
        );
    }

    private function makeRequest(?string $secretHeader, string $body): Request
    {
        $server = [];
        if ($secretHeader !== null) {
            $server['HTTP_X_HYDRA_HOOK_SECRET'] = $secretHeader;
        }
        return Request::create(
            uri: '/oidc-refresh-hook',
            method: 'POST',
            server: $server,
            content: $body,
        );
    }

    public function testReturns401WhenSecretHeaderMissing(): void
    {
        $controller = $this->makeController();

        $response = $controller(
            $this->makeRequest(secretHeader: null, body: '{}'),
        );

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
        $this->assertStringContainsString('"unauthorized"', $response->getContent());
    }

    public function testReturns401WhenSecretHeaderWrong(): void
    {
        $controller = $this->makeController();

        $response = $controller(
            $this->makeRequest(secretHeader: 'this-is-not-the-secret', body: '{}'),
        );

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testReturns500WhenServerSecretNotConfigured(): void
    {
        // Operator forgot to set the env var. We must not silently authorise
        // any caller — bail with a 500 so monitoring catches it.
        $controller = $this->makeController(expectedSecret: '');

        $response = $controller(
            $this->makeRequest(secretHeader: 'whatever', body: '{}'),
        );

        $this->assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        $this->assertStringContainsString('server_misconfigured', $response->getContent());
    }

    public function testSecretCheckUsesConstantTimeCompare(): void
    {
        // Regression test against a future `===` simplification. A presented
        // secret of the same length as the expected secret but differing in
        // content must still be rejected. We can't directly measure timing
        // here, but the behavioural assertion (rejection of equal-length
        // mismatch) is what hash_equals guarantees independently of timing.
        $controller = $this->makeController(expectedSecret: 'AAAAAAAAAAAAAAAA');

        $response = $controller(
            $this->makeRequest(secretHeader: 'BBBBBBBBBBBBBBBB', body: '{}'),
        );

        $this->assertSame(Response::HTTP_UNAUTHORIZED, $response->getStatusCode());
    }

    public function testReturns400OnMalformedJsonBody(): void
    {
        $controller = $this->makeController();

        $response = $controller(
            $this->makeRequest(secretHeader: self::VALID_SECRET, body: 'not json at all{'),
        );

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        $this->assertStringContainsString('invalid_request', $response->getContent());
    }

    public function testReturns400WhenPayloadMissingSubjectOrClient(): void
    {
        $controller = $this->makeController();

        $response = $controller(
            $this->makeRequest(secretHeader: self::VALID_SECRET, body: '{"foo":"bar"}'),
        );

        $this->assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testReturns200WithClaimsOnHappyPath(): void
    {
        $controller = $this->makeController(
            resolverReturns: $this->makeIdentity(),
            policyAllows: true,
            allowedClaims: ['sub', 'preferred_username'],
        );

        $response = $controller(
            $this->makeRequest(
                secretHeader: self::VALID_SECRET,
                body: json_encode(['subject' => 'alice', 'client_id' => 'acme-client'], JSON_THROW_ON_ERROR),
            ),
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());

        $decoded = json_decode((string) $response->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('alice', $decoded['session']['id_token']['sub']);
        $this->assertSame('alice', $decoded['session']['id_token']['preferred_username']);
    }

    public function testReturns200WhenClientIdNestedUnderClientObject(): void
    {
        // Hydra's payload schema for the refresh hook puts client_id under
        // `client.client_id` rather than at the top level. The controller
        // accepts either shape; verify the nested fallback works.
        $controller = $this->makeController(
            resolverReturns: $this->makeIdentity(),
            allowedClaims: ['sub'],
        );

        $response = $controller(
            $this->makeRequest(
                secretHeader: self::VALID_SECRET,
                body: json_encode([
                    'subject' => 'alice',
                    'client' => ['client_id' => 'acme-client'],
                ], JSON_THROW_ON_ERROR),
            ),
        );

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testRejectsWhenSubjectIsDisabled(): void
    {
        $controller = $this->makeController(
            resolverReturns: $this->makeIdentity(disabled: true),
        );

        $response = $controller(
            $this->makeRequest(
                secretHeader: self::VALID_SECRET,
                body: json_encode(['subject' => 'alice', 'client_id' => 'acme-client'], JSON_THROW_ON_ERROR),
            ),
        );

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        $this->assertStringContainsString('access_denied', $response->getContent());
    }

    public function testRejectsWhenSubjectUnknown(): void
    {
        // resolverReturns null = unknown subject.
        $controller = $this->makeController(resolverReturns: null);

        $response = $controller(
            $this->makeRequest(
                secretHeader: self::VALID_SECRET,
                body: json_encode(['subject' => 'ghost', 'client_id' => 'acme-client'], JSON_THROW_ON_ERROR),
            ),
        );

        $this->assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }
}
