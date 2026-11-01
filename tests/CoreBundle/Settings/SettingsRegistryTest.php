<?php

declare(strict_types=1);

namespace App\Tests\CoreBundle\Settings;

use NetBS\CoreBundle\Settings\SettingsBuilder;
use NetBS\CoreBundle\Settings\SettingsProvider;
use NetBS\CoreBundle\Settings\SettingsRegistry;
use PHPUnit\Framework\TestCase;

final class SettingsRegistryTest extends TestCase
{
    public function test_duplicate_key_across_providers_throws(): void
    {
        $registry = new SettingsRegistry([$this->provider('one'), $this->provider('two')]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('app.name');
        $registry->get('app', 'name');
    }

    public function test_get_returns_null_for_undeclared(): void
    {
        $registry = new SettingsRegistry([$this->provider('one')]);

        $this->assertNull($registry->get('app', 'missing'));
        $this->assertSame('app.name', $registry->get('app', 'name')->id());
        $this->assertCount(1, $registry->inSection('one'));
    }

    private function provider(string $section): SettingsProvider
    {
        return new class($section) implements SettingsProvider {
            public function __construct(private string $section)
            {
            }

            public function define(SettingsBuilder $s): void
            {
                $s->section($this->section, ucfirst($this->section))->text('app', 'name', 'Nom', default: 'netBS');
            }
        };
    }
}
