<?php

declare(strict_types=1);

namespace App\Tests\CoreBundle\Settings;

use App\Tests\Support\FichierRowsTrait;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Entity\Parameter;
use NetBS\CoreBundle\Entity\StoredRule;
use NetBS\CoreBundle\Rule\Rule;
use NetBS\CoreBundle\Rule\RuleCondition;
use NetBS\CoreBundle\Rule\RuleGroup;
use NetBS\CoreBundle\Service\ParameterManager;
use NetBS\CoreBundle\Settings\SettingNotConfiguredException;
use NetBS\CoreBundle\Settings\SettingProblem;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ParameterManagerTest extends KernelTestCase
{
    use FichierRowsTrait;

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
        $this->removeFichierRows($this->em);
        parent::tearDown();
    }

    public function test_missing_non_important_returns_default(): void
    {
        $this->assertSame('7', $this->params->getValue('test', 'defaulted_int'));
    }

    public function test_missing_important_throws(): void
    {
        try {
            $this->params->getValue('test', 'important_text');
            $this->fail('Expected SettingNotConfiguredException');
        } catch (SettingNotConfiguredException $e) {
            $this->assertStringContainsString('Texte important', $e->getMessage());
            $this->assertSame('test.important_text', $e->getDefinition()->id());
        }
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

    public function test_soft_deleted_reference_is_broken_and_restore_clears_it(): void
    {
        $groupe = $this->createGroupe($this->em);
        $id     = (string) $groupe->getId();
        $this->params->setValue('test', 'ref', $id);
        $this->assertSame($id, $this->params->getValue('test', 'ref'));
        $this->assertNull($this->problemFor('test.ref'));

        $this->em->remove($groupe);
        $this->em->flush();

        try {
            $this->params->getValue('test', 'ref');
            $this->fail('Expected SettingNotConfiguredException for a trashed reference');
        } catch (SettingNotConfiguredException) {
        }
        $this->assertSame("Référence supprimée (id $id).", $this->problemFor('test.ref')?->message);

        $this->em->getFilters()->disable('softdeleteable');
        $groupe->setDeletedAt(null);
        $this->em->flush();
        $this->em->getFilters()->enable('softdeleteable');
        $this->em->clear();

        $this->assertSame($id, $this->params->getValue('test', 'ref'));
        $this->assertNull($this->problemFor('test.ref'));
    }

    public function test_reference_check_is_memoized_until_next_flush(): void
    {
        $groupe = $this->createGroupe($this->em);
        $id     = (string) $groupe->getId();
        $this->params->setValue('test', 'ref', $id);
        $this->assertSame($id, $this->params->getValue('test', 'ref'));

        // Hard delete behind Doctrine's back: no flush, so the memoized check still answers.
        $this->em->getConnection()->delete('sauvabelin_netbs_groupes', ['id' => $id]);
        $this->assertSame($id, $this->params->getValue('test', 'ref'), 'membre postLoad must not query per row');

        $this->em->flush();

        $this->expectException(SettingNotConfiguredException::class);
        $this->params->getValue('test', 'ref');
    }

    public function test_reset_forgets_reference_checks(): void
    {
        $this->assertInstanceOf(\Symfony\Contracts\Service\ResetInterface::class, $this->params);

        $groupe = $this->createGroupe($this->em);
        $id     = (string) $groupe->getId();
        $this->params->setValue('test', 'ref', $id);
        $this->assertSame($id, $this->params->getValue('test', 'ref'));
        $this->em->getConnection()->delete('sauvabelin_netbs_groupes', ['id' => $id]);

        // The messenger worker resets services between messages without flushing.
        $this->params->reset();

        $this->expectException(SettingNotConfiguredException::class);
        $this->params->getValue('test', 'ref');
    }

    public function test_a_missing_row_is_not_cached(): void
    {
        $this->assertSame('7', $this->params->getValue('test', 'defaulted_int'));

        // e.g. a manual fix on prod through SQL
        $this->em->getConnection()->insert('netbs_core_parameters',
            ['namespace' => 'test', 'paramKey' => 'defaulted_int', 'value' => '12']);

        $this->assertSame('12', $this->params->getValue('test', 'defaulted_int'));
    }

    public function test_blank_stored_important_value_counts_as_missing(): void
    {
        $this->em->getConnection()->insert('netbs_core_parameters',
            ['namespace' => 'test', 'paramKey' => 'important_text', 'value' => '']);

        $this->expectException(SettingNotConfiguredException::class);
        $this->params->getValue('test', 'important_text');
    }

    public function test_problems_lists_missing_important_and_invalid_stored(): void
    {
        $this->assertSame('Non configuré', $this->problemFor('test.important_text')?->message);

        $this->em->getConnection()->insert('netbs_core_parameters',
            ['namespace' => 'test', 'paramKey' => 'defaulted_int', 'value' => 'abc']);

        $this->assertSame('Nombre entier attendu.', $this->problemFor('test.defaulted_int')?->message);
    }

    private function problemFor(string $id): ?SettingProblem
    {
        foreach ($this->params->problems() as $problem) {
            if ($problem->definition->id() === $id) {
                return $problem;
            }
        }

        return null;
    }

    private function deleteTestRows(): void
    {
        $this->em->createQuery('DELETE FROM ' . Parameter::class . ' p WHERE p.namespace = :ns')
            ->setParameter('ns', 'test')->execute();
        $this->em->getConnection()->executeStatement("DELETE FROM netbs_core_rules WHERE code LIKE 'test.%'");
    }

    public function test_missing_rule_setting_is_null_and_not_a_problem(): void
    {
        $this->assertNull($this->params->getValue('test', 'rule'));
        foreach ($this->params->problems() as $problem) {
            $this->assertNotSame('test.rule', $problem->definition->id());
        }
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

    public function test_problems_report_deleted_reference_in_rule_setting(): void
    {
        $groupe = $this->createGroupe($this->em, 'rule-setting groupe');
        $id     = $groupe->getId();
        $this->params->setValue('test', 'rule', Rule::blocks(new RuleGroup('and', [new RuleCondition('test.groupes', ['groupes' => [$id]])]))->toJson());
        $this->em->remove($groupe);
        $this->em->flush();

        $messages = [];
        foreach ($this->params->problems() as $problem) {
            $messages[$problem->definition->id()] = $problem->message;
        }
        $this->assertSame("Référence supprimée (id $id).", $messages['test.rule'] ?? null);
    }
}
