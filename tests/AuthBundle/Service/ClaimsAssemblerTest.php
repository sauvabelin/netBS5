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
            groups: ['users'],
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

        $assembler->assemble($this->makeIdentity(), 'test-client');
    }

    public function testPolicyCannotOverrideStandardEmail(): void
    {
        $assembler = $this->makeAssembler(
            allowedClaims: ['sub', 'email'],
            policy: $this->policyReturning(['email' => 'spoof@evil.example']),
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessageMatches('/reserved claim.*email/i');

        $assembler->assemble($this->makeIdentity(), 'test-client');
    }

    public function testStandardClaimsTakePrecedenceOnUnrelatedPolicyExtras(): void
    {
        $assembler = $this->makeAssembler(
            allowedClaims: ['sub', 'preferred_username', 'nextcloud_admin', 'nextcloud_quota'],
            policy: $this->policyReturning([
                'nextcloud_admin' => true,
                'nextcloud_quota' => '5GB',
            ]),
        );

        $claims = $assembler->assemble($this->makeIdentity(), 'test-client');

        $this->assertSame('alice', $claims['sub']);
        $this->assertSame('alice', $claims['preferred_username']);
        $this->assertTrue($claims['nextcloud_admin']);
        $this->assertSame('5GB', $claims['nextcloud_quota']);
    }

    public function testUserWithoutEmailOmitsTheEmailClaim(): void
    {
        $assembler = $this->makeAssembler(
            allowedClaims: ['sub', 'email', 'preferred_username'],
            policy: $this->policyReturning([]),
        );

        $claims = $assembler->assemble($this->makeIdentity(email: null), 'test-client');

        $this->assertArrayHasKey('sub', $claims);
        $this->assertArrayHasKey('preferred_username', $claims);
        $this->assertArrayNotHasKey('email', $claims, 'email must be omitted when null');
    }

    public function testUserWithEmailEmitsTheEmailClaim(): void
    {
        $assembler = $this->makeAssembler(
            allowedClaims: ['sub', 'email'],
            policy: $this->policyReturning([]),
        );

        $claims = $assembler->assemble($this->makeIdentity(), 'test-client');

        $this->assertSame('alice@example.com', $claims['email']);
    }

    public function testAllowedClaimsFilterRestrictsOutput(): void
    {
        $assembler = $this->makeAssembler(
            allowedClaims: ['sub'],
            policy: $this->policyReturning(['nextcloud_admin' => true]),
        );

        $claims = $assembler->assemble($this->makeIdentity(), 'test-client');

        $this->assertSame(['sub' => 'alice'], $claims);
    }

    public function testNullAdditionalClaimsAreDroppedSilently(): void
    {
        $assembler = $this->makeAssembler(
            allowedClaims: ['sub', 'nextcloud_admin', 'nextcloud_quota'],
            policy: $this->policyReturning([
                'nextcloud_admin' => true,
                'nextcloud_quota' => null,
            ]),
        );

        $claims = $assembler->assemble($this->makeIdentity(), 'test-client');

        $this->assertTrue($claims['nextcloud_admin']);
        $this->assertArrayNotHasKey('nextcloud_quota', $claims);
    }
}
