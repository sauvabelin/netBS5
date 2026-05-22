<?php

namespace NetBS\SecureBundle\DataFixtures;

use Doctrine\Bundle\FixturesBundle\FixtureGroupInterface;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use NetBS\SecureBundle\Entity\Role;
use NetBS\SecureBundle\Service\RoleTreeSyncer;

/**
 * Thin fixture that delegates to {@see RoleTreeSyncer}. The syncer walks
 * every `RoleTreeSourceInterface` registered in the container, so this
 * fixture has no role-data of its own anymore — it just calls into the
 * shared reconciliation path that the `netbs:roles:sync` command also uses.
 *
 * Order 1 keeps the reference-setting step early enough for other fixtures
 * (e.g. LoadUserData) to consume the `ROLE_ADMIN` reference.
 */
class LoadRolesData extends AbstractFixture implements OrderedFixtureInterface, FixtureGroupInterface
{
    public function __construct(private readonly RoleTreeSyncer $syncer)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $this->syncer->syncAll();

        $admin = $manager->getRepository(Role::class)->findOneBy(['role' => 'ROLE_ADMIN']);
        if ($admin !== null) {
            $this->addReference('ROLE_ADMIN', $admin);
        }
    }

    public static function getGroups(): array
    {
        return ['fill', 'main'];
    }

    public function getOrder(): int
    {
        return 1;
    }
}
