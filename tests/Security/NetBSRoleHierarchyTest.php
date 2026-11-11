<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Tests\Support\VoterTestTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use NetBS\SecureBundle\Service\NetBSRoleHierarchy;
use PHPUnit\Framework\TestCase;

/** A role reaches itself and every role below it in the tree, nothing else. */
final class NetBSRoleHierarchyTest extends TestCase
{
    use VoterTestTrait;

    private function hierarchy(): NetBSRoleHierarchy
    {
        $this->role('ROLE_SG'); // builds the tree
        $repository = $this->createMock(EntityRepository::class);
        $repository->method('findAll')->willReturn(array_values($this->roleTree));
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repository);

        return new NetBSRoleHierarchy($em);
    }

    public function test_role_reaches_its_descendants_only(): void
    {
        $reachable = $this->hierarchy()->getReachableRoleNames(['ROLE_SG']);

        $this->assertEqualsCanonicalizing([
            'ROLE_SG',
            'ROLE_CREATE_EVERYWHERE', 'ROLE_CREATE', 'ROLE_CREATE_ATTRIBUTION',
            'ROLE_READ_EVERYWHERE', 'ROLE_READ',
            'ROLE_UPDATE_EVERYWHERE', 'ROLE_UPDATE',
            'ROLE_DELETE_EVERYWHERE', 'ROLE_DELETE',
        ], $reachable);
    }

    public function test_no_roles_reach_nothing(): void
    {
        $this->assertSame([], $this->hierarchy()->getReachableRoleNames([]));
    }

    public function test_unknown_role_reaches_only_itself(): void
    {
        $this->assertSame(['ROLE_FOO'], $this->hierarchy()->getReachableRoleNames(['ROLE_FOO']));
    }

    public function test_several_roles_are_merged_without_duplicates(): void
    {
        $reachable = $this->hierarchy()->getReachableRoleNames(['ROLE_READ_EVERYWHERE', 'ROLE_READ', 'ROLE_USER']);

        $this->assertEqualsCanonicalizing(['ROLE_READ_EVERYWHERE', 'ROLE_READ', 'ROLE_USER'], $reachable);
    }
}
