<?php

declare(strict_types=1);

namespace App\Tests\CoreBundle\Settings;

use NetBS\CoreBundle\Settings\SettingsBuilder;
use NetBS\CoreBundle\Settings\SettingType;
use PHPUnit\Framework\TestCase;

final class SettingsBuilderTest extends TestCase
{
    public function test_declaration_needs_default_xor_important(): void
    {
        $builder = (new SettingsBuilder())->section('app', 'Application');

        try {
            $builder->text('app', 'x', 'X');
            $this->fail('A declaration without default nor important must throw.');
        } catch (\LogicException) {
        }

        $this->expectException(\LogicException::class);
        $builder->text('app', 'y', 'Y', default: 'a', important: true);
    }

    public function test_declaration_outside_section_throws(): void
    {
        $this->expectException(\LogicException::class);
        (new SettingsBuilder())->text('app', 'x', 'X', default: 'a');
    }

    public function test_definitions_carry_section_and_namespace(): void
    {
        $builder = (new SettingsBuilder())
            ->section('application', 'Application')
            ->text('developer', 'name', 'Développeur', default: 'x');

        $definition = $builder->definitions()[0];

        $this->assertSame('application', $definition->section);
        $this->assertSame('developer', $definition->namespace);
        $this->assertSame('developer.name', $definition->id());
        $this->assertSame(SettingType::Text, $definition->type);
        $this->assertSame(['application' => 'Application'], $builder->sections());
    }
}
