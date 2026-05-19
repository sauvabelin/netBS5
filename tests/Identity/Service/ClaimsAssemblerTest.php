<?php

declare(strict_types=1);

namespace App\Tests\Identity\Service;

use App\Identity\Contract\IdentityClientPolicyInterface;
use App\Identity\Contract\IdentityDTO;
use App\Identity\Service\ClaimsAssembler;
use PHPUnit\Framework\TestCase;

final class ClaimsAssemblerTest extends TestCase
{
    public function test_emits_standard_claims_filtered_by_client_allowlist(): void
    {
        $identity = $this->makeIdentity(['foo', 'bar']);
        $policy = $this->makePolicy(additionalClaims: []);

        $clients = [
            'wiki' => ['allowed_claims' => ['sub', 'name']],
        ];

        $assembler = new ClaimsAssembler($clients, $policy);
        $claims = $assembler->assemble($identity, 'wiki');

        self::assertSame(['sub' => 'john.smith', 'name' => 'John Smith'], $claims);
    }

    public function test_emits_groups_claim_when_allowed(): void
    {
        $identity = $this->makeIdentity(['actifs', 'wiki_editors']);
        $policy = $this->makePolicy(additionalClaims: []);

        $clients = [
            'nextcloud' => ['allowed_claims' => ['sub', 'groups']],
        ];

        $assembler = new ClaimsAssembler($clients, $policy);
        $claims = $assembler->assemble($identity, 'nextcloud');

        self::assertSame(['actifs', 'wiki_editors'], $claims['groups']);
    }

    public function test_merges_additional_claims_from_policy(): void
    {
        $identity = $this->makeIdentity([]);
        $policy = $this->makePolicy(additionalClaims: ['wiki_admin' => true]);

        $clients = [
            'wiki' => ['allowed_claims' => ['sub', 'wiki_admin']],
        ];

        $assembler = new ClaimsAssembler($clients, $policy);
        $claims = $assembler->assemble($identity, 'wiki');

        self::assertTrue($claims['wiki_admin']);
    }

    public function test_drops_additional_claims_not_in_allowlist(): void
    {
        $identity = $this->makeIdentity([]);
        $policy = $this->makePolicy(additionalClaims: ['wiki_admin' => true, 'leaked' => 'secret']);

        $clients = [
            'wiki' => ['allowed_claims' => ['sub', 'wiki_admin']],
        ];

        $assembler = new ClaimsAssembler($clients, $policy);
        $claims = $assembler->assemble($identity, 'wiki');

        self::assertArrayNotHasKey('leaked', $claims);
    }

    public function test_throws_on_unknown_client(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $assembler = new ClaimsAssembler(clients: [], policy: $this->makePolicy([]));
        $assembler->assemble($this->makeIdentity([]), 'mystery');
    }

    private function makeIdentity(array $groups): IdentityDTO
    {
        return new IdentityDTO(
            sub: 'john.smith',
            preferredUsername: 'john.smith',
            email: 'john@example.org',
            emailVerified: true,
            displayName: 'John Smith',
            groups: $groups,
            isDisabled: false,
            updatedAt: new \DateTimeImmutable('2026-01-01'),
        );
    }

    private function makePolicy(array $additionalClaims): IdentityClientPolicyInterface
    {
        return new class($additionalClaims) implements IdentityClientPolicyInterface {
            public function __construct(private readonly array $additionalClaims) {}
            public function canAccess(IdentityDTO $i, string $c): bool { return true; }
            public function additionalClaimsFor(IdentityDTO $i, string $c): array { return $this->additionalClaims; }
        };
    }
}
