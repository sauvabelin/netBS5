<?php

declare(strict_types=1);

namespace App\Tests\CoreBundle\Settings;

use App\Tests\Support\FichierRowsTrait;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Settings\SettingDefinition;
use NetBS\CoreBundle\Settings\SettingType;
use NetBS\CoreBundle\Settings\SettingValidator;
use NetBS\CoreBundle\Rule\EntityTargets;
use NetBS\CoreBundle\Rule\Rule;
use NetBS\CoreBundle\Rule\RuleCondition;
use NetBS\CoreBundle\Rule\RuleGroup;
use NetBS\CoreBundle\Rule\RuleValidator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SettingValidatorTest extends KernelTestCase
{
    use FichierRowsTrait;

    private SettingValidator $validator;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->validator = new SettingValidator(
            self::getContainer()->get(EntityManagerInterface::class),
            self::getContainer()->get(EntityTargets::class),
            self::getContainer()->get(RuleValidator::class),
        );
        $this->em        = self::getContainer()->get(EntityManagerInterface::class);
    }

    protected function tearDown(): void
    {
        $this->removeFichierRows($this->em);
        parent::tearDown();
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

        $this->assertSame('Doit être entre 1 et 10000.', $this->validator->validate($int, '0'));
        $this->assertSame('Doit être entre 1 et 10000.', $this->validator->validate($int, '10001'));
        $this->assertNull($this->validator->validate($int, '10000'));
        $this->assertSame('Doit être au moins 0.', $this->validator->validate($this->definition(SettingType::Int, min: 0), '-1'));
    }

    public function test_text_and_secret(): void
    {
        $this->assertSame('Valeur requise.', $this->validator->validate($this->definition(SettingType::Text), ''));
        $this->assertSame('Valeur requise.', $this->validator->validate($this->definition(SettingType::Text), null));
        $this->assertSame('255 caractères maximum.', $this->validator->validate($this->definition(SettingType::Text), str_repeat('a', 256)));
        $this->assertSame('Valeur requise.', $this->validator->validate($this->definition(SettingType::Secret), '   '));
        $this->assertSame('8 caractères minimum.', $this->validator->validate($this->definition(SettingType::Secret, minLength: 8), 'abcdefg'));
        $this->assertNull($this->validator->validate($this->definition(SettingType::Secret, minLength: 8), 'abcdefgh'));
    }

    public function test_secret_rejects_empty_or_short_alternatives(): void
    {
        // galerie.parent_token accepts several passwords separated by "|"; an empty one would
        // let a request without any password in.
        $secret = $this->definition(SettingType::Secret, minLength: 8);

        $this->assertSame('8 caractères minimum pour chaque mot de passe.', $this->validator->validate($secret, 'abcdefgh|'));
        $this->assertSame('8 caractères minimum pour chaque mot de passe.', $this->validator->validate($secret, 'abcdefgh||ijklmnop'));
        $this->assertSame('8 caractères minimum pour chaque mot de passe.', $this->validator->validate($secret, 'abcdefgh|short'));
        $this->assertNull($this->validator->validate($secret, 'abcdefgh|ijklmnop'));
    }

    public function test_choice(): void
    {
        $choice = $this->definition(SettingType::Choice, choices: ['d.m.Y' => '31.12.2026']);

        $this->assertSame('Valeur non autorisée.', $this->validator->validate($choice, 'Y-m-d'));
        $this->assertNull($this->validator->validate($choice, 'd.m.Y'));
    }

    public function test_entity_existing_and_missing(): void
    {
        $ref  = $this->definition(SettingType::Entity, target: 'groupe_type');
        $type = $this->createGroupeType($this->em);

        $this->assertNull($this->validator->validate($ref, (string) $type->getId()));
        $this->assertSame('Référence supprimée (id 999999).', $this->validator->validate($ref, '999999'));
        $this->assertSame('Référence supprimée (id abc).', $this->validator->validate($ref, 'abc'));
    }

    public function test_entity_soft_deleted_is_invalid(): void
    {
        $ref    = $this->definition(SettingType::Entity, target: 'groupe');
        $groupe = $this->createGroupe($this->em);
        $id     = (string) $groupe->getId();

        $this->assertNull($this->validator->validate($ref, $id));

        $this->em->remove($groupe);
        $this->em->flush();
        $this->em->clear();

        $this->assertSame("Référence supprimée (id $id).", $this->validator->validate($ref, $id));
    }

    public function test_validate_all_agrees_with_validate(): void
    {
        $groupe  = $this->createGroupe($this->em);
        $values  = [
            [$this->definition(SettingType::Entity, target: 'groupe', key: 'a'), (string) $groupe->getId()],
            [$this->definition(SettingType::Entity, target: 'groupe', key: 'b'), '999999'],
            [$this->definition(SettingType::Entity, target: 'groupe_type', key: 'c'), 'abc'],
            [$this->definition(SettingType::Int, key: 'd', min: 1), '0'],
            [$this->definition(SettingType::Text, key: 'e'), 'fine'],
        ];

        $expected = [];
        foreach ($values as [$definition, $raw]) {
            if (($error = $this->validator->validate($definition, $raw)) !== null) {
                $expected[$definition->id()] = $error;
            }
        }

        $this->assertCount(3, $expected);
        $this->assertSame($expected, $this->validator->validateAll($values));
    }

    public function test_rule_setting(): void
    {
        $rule = $this->definition(SettingType::Rule, context: 'test', important: false);

        $this->assertNull($this->validator->validate($rule, null));
        $this->assertNull($this->validator->validate($rule, Rule::expert('n > 1')->toJson()));
        $this->assertSame('Condition inconnue : nope.',
            $this->validator->validate($rule, Rule::blocks(new RuleGroup('and', [new RuleCondition('nope', [])]))->toJson()));
    }

    private function definition(SettingType $type, mixed ...$options): SettingDefinition
    {
        return new SettingDefinition(...['namespace' => 'test', 'key' => $options['key'] ?? 'value', 'type' => $type, 'label' => 'Valeur',
            'section' => 'test', 'important' => true, ...array_diff_key($options, ['key' => true])]);
    }
}
