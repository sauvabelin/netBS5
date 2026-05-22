<?php

namespace NetBS\FichierBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * SecureBundle's LoadRolesData (order 1) already calls RoleTreeSyncer, which
 * walks every tagged source — including FichierBundle's FichierRoleTreeSource.
 * So this fixture has nothing left to do; it's kept as an empty stub to
 * preserve any ordering expectations downstream fixtures might have.
 */
class LoadRolesData extends AbstractFixture implements OrderedFixtureInterface, FixtureGroupInterface
{
    public function load(ObjectManager $manager): void
    {
        // No-op: roles are reconciled via the shared RoleTreeSyncer service,
        // invoked from SecureBundle's LoadRolesData (order 1).
    }

    public static function getGroups(): array
    {
        return ['main', 'fill'];
    }

    public function getOrder(): int
    {
        return 300;
    }
}
