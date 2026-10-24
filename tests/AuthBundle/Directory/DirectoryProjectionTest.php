<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Directory;

use NetBS\AuthBundle\Directory\DirectoryNotConfiguredException;
use NetBS\AuthBundle\Directory\DirectoryProjection;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Runs against netbs5_test, inside a transaction that tearDown rolls back.
 */
final class DirectoryProjectionTest extends KernelTestCase
{
    use DirectoryFixturesTrait;

    private DirectoryProjection $projection;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->db = $container->get('doctrine.dbal.default_connection');
        $this->projection = $container->get(DirectoryProjection::class);
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->db->rollBack();
        parent::tearDown();
    }

    public function testRefusesWhenTickedTypesHaveNoGroupes(): void
    {
        $this->db->executeStatement('UPDATE netbs_fichier_groupe_types SET cree_groupe = 0');
        $categorie = $this->insert('netbs_fichier_groupe_categories', ['nom' => 'dirproj-categorie']);
        $this->insert('netbs_fichier_groupe_types', ['nom' => 'dirproj-empty', 'affichageEffectifs' => 0, 'groupeCategorie_id' => $categorie, 'cree_groupe' => 1, 'sous_groupes' => 'tous']);

        $this->expectException(DirectoryNotConfiguredException::class);
        $this->projection->snapshot();
    }

    public function testGroupsFollowTypeSettings(): void
    {
        $ids = $this->seedGroupeTree();

        $groups = $this->projection->snapshot()['groups'];

        self::assertArrayHasKey($ids['ga'], $groups);
        self::assertSame(['dirproj.user'], $groups[$ids['ga']]->members);
        self::assertArrayNotHasKey($ids['gb'], $groups);
        self::assertArrayNotHasKey($ids['gc'], $groups);
    }

    public function testRoleGroupsHoldDirectoryUsersWithTheRole(): void
    {
        $ids = $this->seedGroupeTree();
        $this->grantUserRole($ids['user'], 'ROLE_NEXTCLOUD_ADMIN');
        $this->grantUserRole($this->seedUser('dirproj.inactive', ['isActive' => 0]), 'ROLE_NEXTCLOUD_ADMIN');

        $roleGroups = $this->projection->snapshot()['roleGroups'];

        self::assertSame(['ROLE_NEXTCLOUD_ADMIN', 'ROLE_WIKI_ADMIN'], array_keys($roleGroups));
        self::assertSame('nextcloud-admins', $roleGroups['ROLE_NEXTCLOUD_ADMIN']->name);
        self::assertSame('ROLE_NEXTCLOUD_ADMIN', $roleGroups['ROLE_NEXTCLOUD_ADMIN']->role);
        self::assertSame(['dirproj.user'], $roleGroups['ROLE_NEXTCLOUD_ADMIN']->members);
        self::assertSame([], $roleGroups['ROLE_WIKI_ADMIN']->members, 'always present, even empty');
    }

    public function testGroupNamesAreDnSafeUniqueAndAvoidRoleGroupNames(): void
    {
        $ids = $this->seedGroupeTree();
        $this->db->update('netbs_fichier_groupe_types', ['cree_groupe' => 1], ['nom' => 'dirproj-b']);
        $this->db->update('sauvabelin_netbs_groupes', ['nom' => 'nextcloud-admins'], ['id' => $ids['ga']]);
        $this->db->update('sauvabelin_netbs_groupes', ['nom' => 'Equipe (x), y'], ['id' => $ids['gb']]);
        $this->db->update('sauvabelin_netbs_groupes', ['nom' => 'Equipe [x]  y'], ['id' => $ids['gc']]);

        $groups = $this->projection->snapshot()['groups'];

        self::assertSame(sprintf('nextcloud-admins [%d]', $ids['ga']), $groups[$ids['ga']]->name);
        self::assertSame('Equipe [x]  y', $groups[$ids['gb']]->name);
        self::assertSame(sprintf('Equipe [x]  y [%d]', $ids['gc']), $groups[$ids['gc']]->name);
    }
}
