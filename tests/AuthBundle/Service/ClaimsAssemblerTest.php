<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Service;

use NetBS\AuthBundle\Contract\IdentityDTO;
use NetBS\AuthBundle\Service\ClaimsAssembler;
use PHPUnit\Framework\TestCase;

/**
 * OIDC only carries the standard identity claims, released by scope.
 */
final class ClaimsAssemblerTest extends TestCase
{
    public function testClaimsAreLimitedToGrantedScopes(): void
    {
        $assembler = new ClaimsAssembler();

        $this->assertSame(['sub' => 'alice'], $assembler->assemble($this->identity(), ['openid']));
        $this->assertSame(
            ['sub' => 'alice', 'email' => 'alice@example.com'],
            $assembler->assemble($this->identity(), ['openid', 'email']),
        );
        $this->assertSame(
            ['sub' => 'alice', 'preferred_username' => 'alice', 'name' => 'Alice Example', 'email' => 'alice@example.com'],
            $assembler->assemble($this->identity(), ['openid', 'profile', 'email']),
        );
        $this->assertSame(
            ['sub' => 'alice', 'groups' => ['wiki-admins']],
            $assembler->assemble($this->identity(), ['openid', 'groups']),
        );
    }

    public function testUserWithoutRoleGroupsGetsAnEmptyGroupsClaim(): void
    {
        $claims = (new ClaimsAssembler())->assemble($this->identity(groups: []), ['openid', 'groups']);

        $this->assertSame(['sub' => 'alice', 'groups' => []], $claims);
    }

    public function testUserWithoutEmailOmitsTheEmailClaim(): void
    {
        $claims = (new ClaimsAssembler())->assemble($this->identity(email: null), ['openid', 'email']);

        $this->assertSame(['sub' => 'alice'], $claims);
    }

    /**
     * @param list<string> $groups
     */
    private function identity(?string $email = 'alice@example.com', array $groups = ['wiki-admins']): IdentityDTO
    {
        return new IdentityDTO(
            sub: 'alice',
            preferredUsername: 'alice',
            email: $email,
            displayName: 'Alice Example',
            isDisabled: false,
            groups: $groups,
        );
    }
}
