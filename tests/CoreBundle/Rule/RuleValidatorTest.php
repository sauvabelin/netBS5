<?php

declare(strict_types=1);

namespace App\Tests\CoreBundle\Rule;

use App\Tests\Support\FichierRowsTrait;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Rule\Rule;
use NetBS\CoreBundle\Rule\RuleCondition;
use NetBS\CoreBundle\Rule\RuleGroup;
use NetBS\CoreBundle\Rule\RuleValidator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RuleValidatorTest extends KernelTestCase
{
    use FichierRowsTrait;

    private EntityManagerInterface $em;
    private RuleValidator $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em        = self::getContainer()->get(EntityManagerInterface::class);
        $this->validator = self::getContainer()->get(RuleValidator::class);
    }

    protected function tearDown(): void
    {
        $this->removeFichierRows($this->em);
        parent::tearDown();
    }

    private function rule(RuleGroup|RuleCondition ...$items): Rule
    {
        return Rule::blocks(new RuleGroup('and', $items));
    }

    private function n(array $params): RuleCondition
    {
        return new RuleCondition('test.n', $params);
    }

    public function test_valid_rule_has_no_errors(): void
    {
        $this->assertSame([], $this->validator->validate($this->rule($this->n(['op' => 'ge', 'value' => 3])), 'test'));
        $this->assertSame([], $this->validator->validate(Rule::expert('n > 3'), 'test'));
        $this->assertSame([], $this->validator->validate(null, 'test'));
    }

    public function test_empty_rule_when_not_allowed(): void
    {
        $this->assertCount(1, $this->validator->validate(null, 'test', allowEmpty: false));
        $this->assertCount(1, $this->validator->validate(Rule::expert(' '), 'test', allowEmpty: false));
    }

    public function test_malformed_json(): void
    {
        $this->assertCount(1, $this->validator->validate('{', 'test'));
    }

    public function test_unknown_block(): void
    {
        $errors = $this->validator->validate($this->rule(new RuleCondition('nope', [])), 'test');
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('nope', $errors[0]);
    }

    public function test_block_not_in_context(): void
    {
        $registry = self::getContainer()->get(\NetBS\CoreBundle\Rule\RuleRegistry::class);
        $other    = array_key_first($registry->blocksFor('membre'));
        $this->assertNotNull($other);
        $this->assertCount(1, $this->validator->validate($this->rule(new RuleCondition($other, [])), 'test'));
    }

    public function test_too_many_levels(): void
    {
        $deep = $this->rule(new RuleGroup('or', [new RuleGroup('and', [new RuleCondition('test.flag', [])])]));
        $this->assertCount(1, $this->validator->validate($deep, 'test'));
    }

    public function test_missing_param(): void
    {
        $errors = $this->validator->validate($this->rule($this->n(['op' => 'ge'])), 'test');
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('value', $errors[0]);
    }

    public function test_value_not_allowed(): void
    {
        $errors = $this->validator->validate($this->rule($this->n(['op' => 'xx', 'value' => 1])), 'test');
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('op', $errors[0]);

        $errors = $this->validator->validate($this->rule($this->n(['op' => 'eq', 'value' => 'abc'])), 'test');
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('value', $errors[0]);
    }

    public function test_soft_deleted_groupe_is_a_deleted_reference(): void
    {
        $groupe = $this->createGroupe($this->em, 'rule-validator groupe');
        $id     = $groupe->getId();
        $this->em->remove($groupe);
        $this->em->flush();

        $rule = $this->rule(new RuleCondition('test.groupes', ['groupes' => [$id]]));
        $errors = $this->validator->validate($rule, 'test');
        $this->assertCount(1, $errors);
        $this->assertStringContainsString((string) $id, $errors[0]);
    }

    public function test_live_groupe_is_valid(): void
    {
        $groupe = $this->createGroupe($this->em, 'rule-validator groupe');
        $rule   = $this->rule(new RuleCondition('test.groupes', ['groupes' => [$groupe->getId()]]));
        $this->assertSame([], $this->validator->validate($rule, 'test'));

        $errors = $this->validator->validate($this->rule(new RuleCondition('test.groupes', ['groupes' => []])), 'test');
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('groupes', $errors[0]);
    }

    public function test_invalid_expert_expression(): void
    {
        $errors = $this->validator->validate(Rule::expert('m > 3'), 'test');
        $this->assertCount(1, $errors);
    }

    public function test_empty_nested_group(): void
    {
        $rule = $this->rule($this->n(['op' => 'ge', 'value' => 3]), new RuleGroup('or', []));
        $this->assertCount(1, $this->validator->validate($rule, 'test'));
    }
}
