<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Service;

use NetBS\AuthBundle\Contract\IdentityClientPolicyInterface;
use NetBS\AuthBundle\Contract\IdentityDTO;
use NetBS\AuthBundle\Service\ClaimsAssembler;
use NetBS\AuthBundle\Service\HydraAdminClient;
use PHPUnit\Framework\TestCase;

/**
 * Covers the security/correctness rules in ClaimsAssembler:
 *  1. Policy-supplied claims must never override standard identity claims.
 *  2. email is omitted when the user has no email address on file.
 */
final class ClaimsAssemblerTest extends TestCase
{
    /**
     * @param list<string> $allowedClaims
     */
    private function makeAssembler(
        array $allowedClaims,
        IdentityClientPolicyInterface $policy,
        string $clientId = 'test-client',
    ): ClaimsAssembler {
        // HydraAdminClient is `final` and talks HTTP, so we use reflection to
        // pre-seed the assembler's per-client cache, bypassing the network.
        $hydra = (new \ReflectionClass(HydraAdminClient::class))
            ->newInstanceWithoutConstructor();

        $assembler = new ClaimsAssembler($hydra, $policy);

        $cacheProp = new \ReflectionProperty(ClaimsAssembler::class, 'cache');
        $cacheProp->setAccessible(true);
        $cacheProp->setValue($assembler, [$clientId => $allowedClaims]);

        return $assembler;
    }

    private function makeIdentity(?string $email = 'alice@example.com'): IdentityDTO
    {
        return new IdentityDTO(
            sub: 'alice',
            preferredUsername: 'alice',
            email: $email,
            displayName: 'Alice Example',
            isDisabled: false,
        );
    }

    private function policyReturning(array $additional): IdentityClientPolicyInterface
    {
        return new class($additional) implements IdentityClientPolicyInterface {
            public function __construct(private readonly array $additional) {}
            public function canAccess(IdentityDTO $identity, string $clientId): bool
            {
                return true;
            }
            public function additionalClaimsFor(IdentityDTO $identity, string $clientId): array
            {
                return $this->additional;
            }
        };
    }

    public function testPolicyCannotOverrideStandardSub(): void
    {
        $assembler = $this->makeAssembler(
            allowedClaims: ['sub', 'preferred_username'],
            policy: $this->policyReturning(['sub' => 'attacker']),
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/reserved claim.*sub/i');

        $assembler->assemble($this->makeIdentity(), 'test-client', null);
    }

    public function testUserWithoutEmailOmitsTheEmailClaim(): void
    {
        $assembler = $this->makeAssembler(
            allowedClaims: ['sub', 'email', 'preferred_username'],
            policy: $this->policyReturning([]),
        );

        $claims = $assembler->assemble($this->makeIdentity(email: null), 'test-client', null);

        $this->assertArrayHasKey('sub', $claims);
        $this->assertArrayHasKey('preferred_username', $claims);
        $this->assertArrayNotHasKey('email', $claims, 'email must be omitted when null');
    }

    public function testClaimsAreLimitedToGrantedScopes(): void
    {
        // An openid-only token gets `sub` (and scope-less policy claims) but
        // none of the profile/email claims, even when allowed.
        $assembler = $this->makeAssembler(
            allowedClaims: ['sub', 'preferred_username', 'name', 'email', 'groups', 'custom_flag'],
            policy: $this->policyReturning(['custom_flag' => true]),
        );

        $this->assertSame(
            ['sub' => 'alice', 'custom_flag' => true],
            $assembler->assemble($this->makeIdentity(), 'test-client', ['openid']),
        );

        $claims = $assembler->assemble($this->makeIdentity(), 'test-client', ['openid', 'email', 'groups']);
        $this->assertSame('alice@example.com', $claims['email']);
        // Groups come from LDAP, never from the token, even for clients
        // whose allowed_claims still list `groups`.
        $this->assertArrayNotHasKey('groups', $claims);
        $this->assertArrayNotHasKey('name', $claims);
    }

    public function testAllowedClaimsFilterRestrictsOutput(): void
    {
        $assembler = $this->makeAssembler(
            allowedClaims: ['sub'],
            policy: $this->policyReturning(['custom_flag' => true]),
        );

        $claims = $assembler->assemble($this->makeIdentity(), 'test-client', null);

        $this->assertSame(['sub' => 'alice'], $claims);
    }

}
