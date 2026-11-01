<?php

declare(strict_types=1);

namespace App\Tests\FacturationBundle;

use App\Tests\Support\CollectingLogger;
use App\Tests\Support\InMemoryFactureTrait;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Rule\Rule;
use NetBS\CoreBundle\Rule\RuleCondition;
use NetBS\CoreBundle\Rule\RuleEvaluator;
use NetBS\CoreBundle\Rule\RuleGroup;
use NetBS\CoreBundle\Rule\RuleRegistry;
use Ovesco\FacturationBundle\Entity\FactureModel;
use Ovesco\FacturationBundle\Rule\Block;
use Ovesco\FacturationBundle\Rule\FactureContext;
use Ovesco\FacturationBundle\Service\FactureModelResolver;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

final class FactureModelResolverTest extends TestCase
{
    use InMemoryFactureTrait;

    private const UN_RAPPEL = 'facture.getRappels().count() == 1 && facture.getLatestImpression() !== null';

    private CollectingLogger $logger;
    private FactureModelResolver $resolver;

    protected function setUp(): void
    {
        $this->logger   = new CollectingLogger();
        $registry       = new RuleRegistry([new FactureContext()], [
            new Block\NombreRappelsBlock(), new Block\ImprimeeBlock(), new Block\DebiteurFamilleBlock(), new Block\NombreCreancesBlock(),
        ]);
        $this->resolver = new FactureModelResolver(
            $this->createMock(EntityManagerInterface::class),
            new RuleEvaluator($registry, $this->logger),
            $this->logger,
        );
    }

    private function model(string $name, int $poids, ?string $legacy = null, ?string $ruleJson = null): FactureModel
    {
        $model = new FactureModel();
        $model->setName($name);
        $model->setPoids($poids);
        if ($ruleJson !== null) {
            $model->setRuleJson($ruleJson);
        }
        $model->setApplicationRule($legacy);

        return $model;
    }

    private function chosen(array $models, $facture): string
    {
        return $this->resolver->choose($models, $facture)->getName();
    }

    public function test_legacy_expression_still_applies(): void
    {
        $models = [$this->model('1 rappel', 10, self::UN_RAPPEL), $this->model('Par défaut', 0)];

        $this->assertSame('1 rappel', $this->chosen($models, $this->facture(rappels: 1, printed: true)));
        $this->assertSame('Par défaut', $this->chosen($models, $this->facture(rappels: 1)));
        $this->assertSame('Par défaut', $this->chosen($models, $this->facture(rappels: 0, printed: true)));
        $this->assertSame('Par défaut', $this->chosen($models, $this->facture(rappels: 2, printed: true)));
    }

    public function test_rule_row_wins_over_legacy(): void
    {
        $rule   = Rule::blocks(new RuleGroup('and', [new RuleCondition('facture.nombre_rappels', ['op' => 'ge', 'nombre' => 5])]))->toJson();
        $models = [$this->model('Règle', 10, 'true', $rule), $this->model('Par défaut', 0)];

        $this->assertSame('Par défaut', $this->chosen($models, $this->facture(rappels: 1)));
        $this->assertSame('Règle', $this->chosen($models, $this->facture(rappels: 5)));
    }

    public function test_empty_rule_always_applies(): void
    {
        $models = [$this->model('Toujours', 10), $this->model('Jamais choisi', 0, 'true')];

        $this->assertSame('Toujours', $this->chosen($models, $this->facture()));
    }

    public function test_fallback_to_highest_poids(): void
    {
        $models = [$this->model('Haut', 10, 'false'), $this->model('Bas', 0, 'false')];

        $this->assertSame('Haut', $this->chosen($models, $this->facture()));
    }

    public function test_expert_exception_counts_as_no_match(): void
    {
        $models = [
            $this->model('Cassé', 10, 'facture.getLatestImpression().format("d.m.Y") == "01.01.2026"'),
            $this->model('Par défaut', 0),
        ];

        $this->assertSame('Par défaut', $this->chosen($models, $this->facture()));
        $this->assertSame(1, $this->logger->count(LogLevel::WARNING));
    }

    public function test_no_model_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Aucun modèle de facture trouvé en base de données');
        $this->resolver->choose([], $this->facture());
    }

    public function test_malformed_rule_row_counts_as_no_match(): void
    {
        $broken = $this->model('Cassé', 10);
        $broken->setRule(new \NetBS\CoreBundle\Entity\StoredRule('facture', '{"mode":"blocks"'));
        $models = [$broken, $this->model('Par défaut', 0)];

        $this->assertSame('Par défaut', $this->chosen($models, $this->facture()));
        $this->assertSame(1, $this->logger->count(LogLevel::WARNING));
    }

}
