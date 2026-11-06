<?php

declare(strict_types=1);

namespace App\Tests\Settings;

use App\Tests\Support\FichierRowsTrait;
use App\Tests\Support\UserFactoryTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * netbs5_test has no bs.* / galerie.* settings at all: pages and endpoints that read one must
 * degrade quietly, not 500.
 */
final class UnconfiguredSettingsTest extends WebTestCase
{
    use FichierRowsTrait;
    use UserFactoryTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em     = static::getContainer()->get(EntityManagerInterface::class);
        static::getContainer()->get('cache.app')->clear();
    }

    protected function tearDown(): void
    {
        $this->removeFichierRows($this->em);
        parent::tearDown();
    }

    public function test_groupe_page_renders(): void
    {
        $groupe = $this->createGroupe($this->em, 'unconfigured-settings groupe');
        $this->client->loginUser($this->persistUser($this->client, 'unconfigured-admin', null, null, ['ROLE_USER', 'ROLE_ADMIN']), 'netbs');

        $this->client->request('GET', '/netBS/fichier/groupe/' . $groupe->getId());

        $this->assertResponseIsSuccessful();
    }

    public function test_galerie_directory_denies_without_parent_token(): void
    {
        $this->client->request('GET', '/api/v1/public/netBS/galerie/directory');
        $this->assertResponseStatusCodeSame(401);

        $this->client->request('GET', '/api/v1/public/netBS/galerie/directory', [], [], ['HTTP_X_AUTHORIZATION' => 'Bearer ']);
        $this->assertResponseStatusCodeSame(401);
    }
}
