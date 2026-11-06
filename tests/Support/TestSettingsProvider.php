<?php

namespace App\Tests\Support;

use NetBS\CoreBundle\Settings\SettingsBuilder;
use NetBS\CoreBundle\Settings\SettingsProvider;

/**
 * Settings only declared in the test environment (config/services_test.yaml), so the core
 * behaviour can be exercised without depending on the real declarations.
 */
class TestSettingsProvider implements SettingsProvider
{
    public function define(SettingsBuilder $s): void
    {
        $s->section('test', 'Test')
            ->text('test', 'plain_text', 'Texte sans défaut')
            ->int('test', 'defaulted_int', 'Entier par défaut', default: 7, min: 1)
            ->rule('test', 'rule', 'Règle test', 'test', help: 'Une règle de test');
    }
}
