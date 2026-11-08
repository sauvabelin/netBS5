<?php

declare(strict_types=1);

namespace App\Tests\CoreBundle\Command;

use App\Tests\Support\FichierRowsTrait;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Entity\StoredRule;
use NetBS\CoreBundle\Rule\Rule;
use NetBS\CoreBundle\Rule\RuleCondition;
use NetBS\CoreBundle\Rule\RuleGroup;
use NetBS\AuthBundle\Entity\DirectoryPublicationRule;
use Ovesco\FacturationBundle\Entity\FactureModel;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class RulesCheckCommandTest extends KernelTestCase
{
    use FichierRowsTrait;

    private EntityManagerInterface $em;
    /** @var list<int> */
    private array $publicationRuleIds = [];
    /** @var list<int> */
    private array $storedRuleIds = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        $this->em->clear();
        $this->removeFichierRows($this->em);
        parent::tearDown();
    }

    private function cleanUp(): void
    {
        // only rows this test created: other tests share the DB
        $connection = $this->em->getConnection();
        $connection->executeStatement("DELETE FROM ovesco_facturation_facture_models WHERE name LIKE 'rules-check%'");
        if ($this->publicationRuleIds !== []) {
            $connection->executeStatement(
                'DELETE FROM netbs_auth_directory_publication_rules WHERE id IN (?)',
                [$this->publicationRuleIds],
                [ArrayParameterType::INTEGER],
            );
        }
        if ($this->storedRuleIds !== []) {
            $connection->executeStatement('DELETE FROM netbs_core_rules WHERE id IN (?)', [$this->storedRuleIds], [ArrayParameterType::INTEGER]);
        }
        $this->publicationRuleIds = $this->storedRuleIds = [];
    }

    private function runCheck(): CommandTester
    {
        $tester = new CommandTester((new Application(self::$kernel))->find('netbs:rules:check'));
        $tester->execute([]);

        return $tester;
    }

    private function display(CommandTester $tester): string
    {
        return preg_replace('/\s+/', ' ', $tester->getDisplay());
    }

    public function test_clean_succeeds(): void
    {
        $tester = $this->runCheck();

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
    }

    public function test_publication_rule_with_deleted_fonction_fails(): void
    {
        $fonction = $this->createFonction($this->em, 'rules-check cp');
        $id       = $fonction->getId();
        $type     = $this->createGroupeType($this->em, 'rules-check type');
        $rule     = new DirectoryPublicationRule();
        $rule->setLabel('rules-check unités');
        $rule->setPosition(1);
        $rule->setSelectionJson(Rule::blocks(new RuleGroup('and', [new RuleCondition('groupe.types', ['types' => [$type->getId()]])]))->toJson());
        $rule->setSousGroupes(DirectoryPublicationRule::SOUS_GROUPES_REGLE);
        $rule->setSousGroupesRuleJson(Rule::blocks(new RuleGroup('and', [new RuleCondition('attribution.fonctions', ['fonctions' => [$id]])]))->toJson());
        $this->em->persist($rule);
        $this->em->remove($fonction);
        $this->em->flush();
        $this->publicationRuleIds[] = $rule->getId();
        $this->storedRuleIds[]      = $rule->getSelection()->getId();
        $this->storedRuleIds[]      = $rule->getSousGroupesRule()->getId();

        $tester = $this->runCheck();

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('rules-check unités', $this->display($tester));
    }

    public function test_invalid_legacy_expression_fails(): void
    {
        $model = new FactureModel();
        foreach (['setName' => 'rules-check modèle', 'setTitre' => 'T', 'setTopDescription' => 'H', 'setBottomSalutations' => 'B',
                  'setSignataire' => 'S', 'setGroupName' => 'G', 'setRue' => 'R', 'setNpaVille' => 'N', 'setCityFrom' => 'C'] as $setter => $value) {
            $model->$setter($value);
        }
        $model->setPoids(1);
        $model->setApplicationRule('facture.getRappels(.count()');
        $this->em->persist($model);
        $this->em->flush();

        $tester = $this->runCheck();

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertStringContainsString('rules-check modèle', $this->display($tester));
    }

    public function test_orphan_row_is_reported(): void
    {
        $row = new StoredRule('membre', Rule::empty()->toJson());
        $this->em->persist($row);
        $this->em->flush();
        $this->storedRuleIds[] = $row->getId();

        $tester = $this->runCheck();

        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
        $this->assertMatchesRegularExpression('/\\b' . $row->getId() . '\\b/', $this->display($tester));
    }
}
