<?php

declare(strict_types=1);

namespace App\Tests\CoreBundle\Controller;

use App\Tests\Support\FichierRowsTrait;
use App\Tests\Support\ParametersSnapshotTrait;
use App\Tests\Support\UserFactoryTrait;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Service\ParameterManager;
use NetBS\CoreBundle\Settings\SettingsRegistry;
use NetBS\CoreBundle\Settings\SettingType;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SettingsBannerTest extends WebTestCase
{
    use UserFactoryTrait;
    use FichierRowsTrait;
    use ParametersSnapshotTrait;

    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em     = $this->client->getContainer()->get('doctrine.orm.entity_manager');
        $this->emptyParameters($this->em);
    }

    protected function tearDown(): void
    {
        $this->restoreParameters($this->em);
        $this->removeFichierRows($this->em);
        parent::tearDown();
    }

    public function test_admin_sees_missing_important_settings(): void
    {
        $this->login(['ROLE_USER', 'ROLE_ADMIN']);

        $crawler = $this->client->request('GET', $this->path('netbs.core.parameters.list'));

        $banner = $crawler->filter('.alert-danger');
        $this->assertCount(1, $banner);
        $this->assertStringContainsString('Mot de passe parents', $banner->text());
        $this->assertCount(1, $banner->filter('a[href$="#setting-galerie-parent_token"]'));
    }

    public function test_member_never_sees_the_banner(): void
    {
        $this->login(['ROLE_USER']);

        $this->client->request('GET', $this->path('netbs.core.home.dashboard'));

        $this->assertResponseIsSuccessful();
        $this->assertStringNotContainsString('paramètre(s) important(s)', $this->client->getResponse()->getContent());
    }

    public function test_no_banner_when_everything_is_configured(): void
    {
        $this->configureEveryImportantSetting();
        $this->login(['ROLE_USER', 'ROLE_ADMIN']);

        $this->client->request('GET', $this->path('netbs.core.parameters.list'));

        $this->assertResponseIsSuccessful();
        $this->assertStringNotContainsString('paramètre(s) important(s)', $this->client->getResponse()->getContent());
    }

    private function configureEveryImportantSetting(): void
    {
        $params = $this->client->getContainer()->get(ParameterManager::class);
        $groupe = $this->createGroupe($this->em);
        $ids    = [
            'groupe'           => $groupe->getId(),
            'groupe_type'      => $groupe->getGroupeType()->getId(),
            'groupe_categorie' => $groupe->getGroupeType()->getGroupeCategorie()->getId(),
            'fonction'         => $this->createFonction($this->em)->getId(),
        ];

        foreach ($this->client->getContainer()->get(SettingsRegistry::class)->all() as $definition) {
            if (!$definition->important) {
                continue;
            }

            $params->setValue($definition->namespace, $definition->key, match ($definition->type) {
                SettingType::Entity => (string) $ids[$definition->target],
                SettingType::Int    => (string) ($definition->min ?? 1),
                default             => 'configured-value',
            });
        }
    }

    private function login(array $roles): void
    {
        $user = $this->persistUser($this->client, 'settings-banner-user', roles: $roles);
        $this->client->loginUser($user, 'netbs');
    }

    private function path(string $route): string
    {
        return $this->client->getContainer()->get('router')->generate($route);
    }
}
