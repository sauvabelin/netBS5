<?php

declare(strict_types=1);

namespace App\Tests\CoreBundle\Command;

use App\Tests\Support\FichierRowsTrait;
use App\Tests\Support\ParametersSnapshotTrait;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Service\ParameterManager;
use NetBS\CoreBundle\Settings\SettingsRegistry;
use NetBS\CoreBundle\Settings\SettingType;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class SettingsCheckCommandTest extends KernelTestCase
{
    use FichierRowsTrait;
    use ParametersSnapshotTrait;

    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->emptyParameters($this->em);
    }

    protected function tearDown(): void
    {
        $this->restoreParameters($this->em);
        $this->removeFichierRows($this->em);
        parent::tearDown();
    }

    public function test_all_configured_succeeds(): void
    {
        $this->configureEveryImportantSetting();

        $tester = $this->runCheck();

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Tous les paramètres sont valides.', $tester->getDisplay());
    }

    public function test_missing_important_fails_and_names_it(): void
    {
        $this->configureEveryImportantSetting();
        $this->em->getConnection()->executeStatement(
            "DELETE FROM netbs_core_parameters WHERE namespace = 'galerie' AND paramKey = 'parent_token'");

        $tester = $this->runCheck();

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('galerie.parent_token (Mot de passe parents): Non configuré', $tester->getDisplay());
    }

    private function runCheck(): CommandTester
    {
        $tester = new CommandTester((new Application(self::$kernel))->find('netbs:settings:check'));
        $tester->execute([]);

        return $tester;
    }

    private function configureEveryImportantSetting(): void
    {
        $params = self::getContainer()->get(ParameterManager::class);
        $groupe = $this->createGroupe($this->em);
        $ids    = [
            'groupe'           => $groupe->getId(),
            'groupe_type'      => $groupe->getGroupeType()->getId(),
            'groupe_categorie' => $groupe->getGroupeType()->getGroupeCategorie()->getId(),
            'fonction'         => $this->createFonction($this->em)->getId(),
        ];

        foreach (self::getContainer()->get(SettingsRegistry::class)->all() as $definition) {
            if ($definition->important) {
                $params->setValue($definition->namespace, $definition->key, match ($definition->type) {
                    SettingType::Entity => (string) $ids[$definition->target],
                    SettingType::Int    => (string) ($definition->min ?? 1),
                    default             => 'configured-value',
                });
            }
        }
    }
}
