<?php

declare(strict_types=1);

namespace App\Tests\Settings;

use App\Automatics\CotisationsAutomatic;
use App\Tests\Support\CollectingLogger;
use App\Tests\Support\InMemoryFichierTrait;
use App\Tests\Support\RealRuleEngineTrait;
use NetBS\CoreBundle\Rule\Rule;
use NetBS\CoreBundle\Rule\RuleCondition;
use NetBS\CoreBundle\Rule\RuleGroup;
use NetBS\CoreBundle\Service\ParameterManager;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;

/**
 * Membres: 1 in a patrouille of the Someo branche, 2 with a poids-150 fonction, 3 neither,
 * 4 directly in Someo.
 */
final class CotisationsRuleTest extends TestCase
{
    use InMemoryFichierTrait, RealRuleEngineTrait;

    private array $membres;

    protected function setUp(): void
    {
        $someo      = $this->groupe(10);
        $patrouille = $this->groupe(11, parent: $someo);
        $troupe     = $this->groupe(12);
        $chef       = $this->fonction(1, 150);
        $eclaireur  = $this->fonction(2, 10);

        $this->membres = [1 => $this->membre(1), 2 => $this->membre(2), 3 => $this->membre(3), 4 => $this->membre(4)];
        $this->attribute($this->membres[1], $patrouille, $eclaireur);
        $this->attribute($this->membres[2], $troupe, $chef);
        $this->attribute($this->membres[3], $troupe, $eclaireur);
        $this->attribute($this->membres[4], $someo, $eclaireur);
    }

    private function split(?string $rule, ?CollectingLogger $logger = null): array
    {
        $params = $this->createMock(ParameterManager::class);
        $params->method('getValue')->willReturnMap([['bs', 'cotisations.tarif_reduit', true, $rule]]);
        $params->method('getStoredValue')->willReturnMap([['bs', 'groupe.branche_smt_id', '10']]);

        $automatic = new CotisationsAutomatic();
        $automatic->setParameterManager($params);
        $automatic->setRuleEvaluator($this->fichierRuleEvaluator($logger));

        $split = $automatic->splitTarif(array_values($this->membres));
        $ids   = fn (array $membres) => array_map(fn ($m) => $m->getId(), $membres);

        return ['normal' => $ids($split['normal']), 'reduit' => $ids($split['reduit'])];
    }

    public function test_without_rule_uses_legacy_logic(): void
    {
        $this->assertSame(['normal' => [3], 'reduit' => [1, 2, 4]], $this->split(null));
    }

    public function test_rule_overrides_legacy(): void
    {
        $rule = Rule::blocks(new RuleGroup('and', [new RuleCondition('membre.fonctions', ['fonctions' => [1]])]))->toJson();

        $this->assertSame(['normal' => [1, 3, 4], 'reduit' => [2]], $this->split($rule));
    }

    public function test_expert_error_does_not_break_list(): void
    {
        $logger = new CollectingLogger();

        $this->assertSame(['normal' => [1, 2, 3, 4], 'reduit' => []], $this->split(Rule::expert('membre.foo()')->toJson(), $logger));
        $this->assertSame(4, $logger->count(LogLevel::WARNING));
    }
}
