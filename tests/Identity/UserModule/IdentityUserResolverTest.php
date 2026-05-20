<?php

declare(strict_types=1);

namespace App\Tests\Identity\UserModule;

use App\Entity\BSUser;
use App\Identity\UserModule\IdentityGroupProvider;
use App\Identity\UserModule\IdentityUserResolver;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use NetBS\AuthBundle\Contract\IdentityGroupProviderInterface;
use PHPUnit\Framework\TestCase;

/**
 * Regression test for the duplicate-query fix:
 *   - The resolver must fetch BSUser only once per resolveBySub().
 *   - The group provider receives the already-loaded entity instead of
 *     re-querying it by sub.
 */
final class IdentityUserResolverTest extends TestCase
{
    public function testResolveBySubIssuesExactlyOneDoctrineFetch(): void
    {
        $user = $this->createMock(BSUser::class);
        $user->method('getUsername')->willReturn('alice');
        $user->method('getMembre')->willReturn(null);
        $user->method('getEmail')->willReturn('alice@example.com');
        $user->method('getEmailBS')->willReturn(null);
        $user->method('getIsActive')->willReturn(true);

        $repo = $this->createMock(EntityRepository::class);
        // The crux of the test: findOneBy must be called once and only once.
        $repo->expects($this->once())
            ->method('findOneBy')
            ->with(['username' => 'alice'])
            ->willReturn($user);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('getRepository')
            ->with(BSUser::class)
            ->willReturn($repo);

        // Counting group provider: must be called exactly once and must
        // receive the already-loaded entity (not a sub string or DTO).
        $groupCalls = 0;
        $receivedUser = null;
        $groupProvider = new class($groupCalls, $receivedUser) implements IdentityGroupProviderInterface {
            public function __construct(private int &$calls, private mixed &$received) {}
            public function groupsFor(object $user): array
            {
                $this->calls++;
                $this->received = $user;
                return ['testers'];
            }
        };

        $resolver = new IdentityUserResolver($em, $groupProvider);
        $dto = $resolver->resolveBySub('alice');

        $this->assertNotNull($dto);
        $this->assertSame('alice', $dto->sub);
        $this->assertSame('alice@example.com', $dto->email);
        $this->assertTrue($dto->emailVerified);
        $this->assertSame(['testers'], $dto->groups);
        $this->assertFalse($dto->isDisabled);
        $this->assertSame(1, $groupCalls, 'group provider should be called exactly once');
        $this->assertSame($user, $receivedUser, 'group provider should receive the loaded BSUser entity');
    }

    public function testResolveBySubReturnsNullForUnknownUser(): void
    {
        $repo = $this->createMock(EntityRepository::class);
        $repo->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $groupProvider = $this->createMock(IdentityGroupProviderInterface::class);
        // Must NOT be called when the user is unknown.
        $groupProvider->expects($this->never())->method('groupsFor');

        $resolver = new IdentityUserResolver($em, $groupProvider);

        $this->assertNull($resolver->resolveBySub('ghost'));
    }

    public function testGroupProviderReturnsEmptyForNonBSUserObject(): void
    {
        // Defensive: contract is typed `object` to keep AuthBundle free of
        // BSUser. The concrete provider must narrow at runtime.
        $provider = new IdentityGroupProvider();
        $this->assertSame([], $provider->groupsFor(new \stdClass()));
    }
}
