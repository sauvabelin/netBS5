<?php

declare(strict_types=1);

namespace App\Tests\Ldap;

use App\Ldap\DirectoryNotConfiguredException;
use App\Ldap\DirectoryProjection;
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

    public function testRefusesWhenNoTypeCreatesAGroup(): void
    {
        $this->db->executeStatement('UPDATE netbs_fichier_groupe_types SET cree_groupe = 0');

        $this->expectException(DirectoryNotConfiguredException::class);
        $this->projection->snapshot();
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
}
