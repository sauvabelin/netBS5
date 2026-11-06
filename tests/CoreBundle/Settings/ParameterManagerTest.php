<?php

declare(strict_types=1);

namespace App\Tests\CoreBundle\Settings;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Entity\Parameter;
use NetBS\CoreBundle\Entity\StoredRule;
use NetBS\CoreBundle\Rule\Rule;
use NetBS\CoreBundle\Rule\RuleCondition;
use NetBS\CoreBundle\Rule\RuleGroup;
use NetBS\CoreBundle\Service\ParameterManager;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ParameterManagerTest extends KernelTestCase
{
    private ParameterManager $params;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->params = self::getContainer()->get(ParameterManager::class);
        $this->em     = self::getContainer()->get(EntityManagerInterface::class);
        $this->deleteTestRows();
        self::getContainer()->get('cache.app')->clear();
    }

    protected function tearDown(): void
    {
        $this->deleteTestRows();
        parent::tearDown();
    }

    public function test_missing_returns_default(): void
    {
        $this->assertSame('7', $this->params->getValue('test', 'defaulted_int'));
    }

    public function test_undeclared_missing_returns_null(): void
    {
        $this->assertNull($this->params->getValue('test', 'not_declared'));
    }

    public function test_set_value_validates_and_creates_row(): void
    {
        try {
            $this->params->setValue('test', 'defaulted_int', '0');
            $this->fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            $this->assertSame('Doit être au moins 1.', $e->getMessage());
        }

        $this->params->setValue('test', 'defaulted_int', '12');

        $this->assertSame('12', $this->params->getValue('test', 'defaulted_int'));
        $this->assertSame('12', $this->params->getValue('test', 'defaulted_int', false));
    }

    public function test_a_missing_row_is_not_cached(): void
    {
        $this->assertSame('7', $this->params->getValue('test', 'defaulted_int'));

        // e.g. a manual fix on prod through SQL
        $this->em->getConnection()->insert('netbs_core_parameters',
            ['namespace' => 'test', 'paramKey' => 'defaulted_int', 'value' => '12']);

        $this->assertSame('12', $this->params->getValue('test', 'defaulted_int'));
    }

    private function deleteTestRows(): void
    {
        $this->em->createQuery('DELETE FROM ' . Parameter::class . ' p WHERE p.namespace = :ns')
            ->setParameter('ns', 'test')->execute();
        $this->em->getConnection()->executeStatement("DELETE FROM netbs_core_rules WHERE code LIKE 'test.%'");
    }

    public function test_missing_rule_setting_is_null(): void
    {
        $this->assertNull($this->params->getValue('test', 'rule'));
    }

    public function test_missing_without_default_is_null(): void
    {
        $this->assertNull($this->params->getValue('test', 'plain_text'));
    }

    public function test_rule_setting_round_trips_through_rules_table(): void
    {
        $json = Rule::blocks(new RuleGroup('and', [new RuleCondition('test.n', ['op' => 'ge', 'value' => 3])]))->toJson();
        $this->params->setValue('test', 'rule', $json);

        $this->assertSame($json, $this->params->getValue('test', 'rule'));
        $this->assertSame('test', $this->em->getRepository(StoredRule::class)->findOneByCode('test.rule')->getContext());
        $this->assertFalse($this->em->getConnection()->fetchOne("SELECT 1 FROM netbs_core_parameters WHERE namespace = 'test' AND paramKey = 'rule'"));

        $this->params->setValue('test', 'rule', null);
        $this->assertNull($this->params->getValue('test', 'rule'));
        $this->assertNull($this->em->getRepository(StoredRule::class)->findOneByCode('test.rule'));
    }

    public function test_invalid_rule_setting_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Condition inconnue : nope.');
        $this->params->setValue('test', 'rule', Rule::blocks(new RuleGroup('and', [new RuleCondition('nope', [])]))->toJson());
    }
}
