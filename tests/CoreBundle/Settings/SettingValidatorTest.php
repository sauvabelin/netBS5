<?php

declare(strict_types=1);

namespace App\Tests\CoreBundle\Settings;

use NetBS\CoreBundle\Settings\SettingDefinition;
use NetBS\CoreBundle\Settings\SettingType;
use NetBS\CoreBundle\Settings\SettingValidator;
use NetBS\CoreBundle\Rule\Rule;
use NetBS\CoreBundle\Rule\RuleCondition;
use NetBS\CoreBundle\Rule\RuleGroup;
use NetBS\CoreBundle\Rule\RuleValidator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SettingValidatorTest extends KernelTestCase
{
    private SettingValidator $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validator = new SettingValidator(self::getContainer()->get(RuleValidator::class));
    }

    public function test_int_rejects_loose_numeric_strings(): void
    {
        $int = $this->definition(SettingType::Int);

        foreach (['12abc', '1e3', ' 5', '', '5.0'] as $raw) {
            $this->assertNotNull($this->validator->validate($int, $raw), "'$raw' must be rejected");
        }
        $this->assertNull($this->validator->validate($int, '5'));
        $this->assertNull($this->validator->validate($int, '-3'));
    }

    public function test_int_bounds(): void
    {
        $int = $this->definition(SettingType::Int, min: 1, max: 10000);

        $this->assertNotNull($this->validator->validate($int, '0'));
        $this->assertNotNull($this->validator->validate($int, '10001'));
        $this->assertNull($this->validator->validate($int, '10000'));
        $this->assertNotNull($this->validator->validate($this->definition(SettingType::Int, min: 0), '-1'));
    }

    public function test_text_and_secret(): void
    {
        $this->assertNotNull($this->validator->validate($this->definition(SettingType::Text), ''));
        $this->assertNotNull($this->validator->validate($this->definition(SettingType::Text), null));
        $this->assertNotNull($this->validator->validate($this->definition(SettingType::Text), str_repeat('a', 256)));
        $this->assertNotNull($this->validator->validate($this->definition(SettingType::Secret), '   '));
        $this->assertNotNull($this->validator->validate($this->definition(SettingType::Secret, minLength: 8), 'abcdefg'));
        $this->assertNull($this->validator->validate($this->definition(SettingType::Secret, minLength: 8), 'abcdefgh'));
    }

    public function test_secret_rejects_empty_or_short_alternatives(): void
    {
        // galerie.parent_token accepts several passwords separated by "|"; an empty one would
        // let a request without any password in.
        $secret = $this->definition(SettingType::Secret, minLength: 8);

        $this->assertNotNull($this->validator->validate($secret, 'abcdefgh|'));
        $this->assertNotNull($this->validator->validate($secret, 'abcdefgh||ijklmnop'));
        $this->assertNotNull($this->validator->validate($secret, 'abcdefgh|short'));
        $this->assertNull($this->validator->validate($secret, 'abcdefgh|ijklmnop'));
    }

    public function test_choice(): void
    {
        $choice = $this->definition(SettingType::Choice, choices: ['d.m.Y' => '31.12.2026']);

        $this->assertNotNull($this->validator->validate($choice, 'Y-m-d'));
        $this->assertNull($this->validator->validate($choice, 'd.m.Y'));
    }

    public function test_rule_setting(): void
    {
        $rule = $this->definition(SettingType::Rule, context: 'test');

        $this->assertNull($this->validator->validate($rule, null));
        $this->assertNull($this->validator->validate($rule, Rule::expert('n > 1')->toJson()));
        $this->assertNotNull($this->validator->validate($rule, Rule::blocks(new RuleGroup('and', [new RuleCondition('nope', [])]))->toJson()));
    }

    private function definition(SettingType $type, mixed ...$options): SettingDefinition
    {
        return new SettingDefinition(...['namespace' => 'test', 'key' => $options['key'] ?? 'value', 'type' => $type, 'label' => 'Valeur',
            'section' => 'test', ...array_diff_key($options, ['key' => true])]);
    }
}
