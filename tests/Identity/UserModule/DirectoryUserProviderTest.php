<?php

declare(strict_types=1);

namespace App\Tests\Identity\UserModule;

use App\Identity\UserModule\DirectoryUserProvider;
use App\Tests\AuthBundle\Directory\DirectoryFixturesTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Which directory roles (DirectoryProjection::ROLE_GROUPS) each directory user
 * holds. Runs against netbs5_test, inside a rolled-back transaction.
 */
final class DirectoryUserProviderTest extends KernelTestCase
{
    use DirectoryFixturesTrait;

    private DirectoryUserProvider $provider;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->db = $container->get('doctrine.dbal.default_connection');
        $this->provider = $container->get(DirectoryUserProvider::class);
        $this->db->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->db->rollBack();
        parent::tearDown();
    }

    public function testRolesFromDirectGrantAndActiveFonction(): void
    {
        $ids = $this->seedGroupeTree();
        $this->grantUserRole($ids['user'], 'ROLE_ADMIN');
        $this->grantUserRole($ids['user'], 'ROLE_WIKI_ADMIN');
        $this->grantFonctionRole($ids['fonction'], 'ROLE_NEXTCLOUD_ADMIN');

        self::assertSame(['ROLE_NEXTCLOUD_ADMIN', 'ROLE_WIKI_ADMIN'], $this->provider->directoryUsers()['dirproj.user']->roles);
    }

    public function testEndedAttributionDoesNotCount(): void
    {
        $ids = $this->seedGroupeTree();
        $this->grantFonctionRole($ids['fonction'], 'ROLE_NEXTCLOUD_ADMIN');
        $this->db->update('netbs_fichier_attributions', ['dateFin' => '2021-01-01'], ['id' => $ids['attribution']]);

        self::assertSame([], $this->provider->directoryUsers()['dirproj.user']->roles);
    }
}
