<?php

namespace App\Settings;

use NetBS\CoreBundle\Settings\SettingsBuilder;
use NetBS\CoreBundle\Settings\SettingsProvider;

/**
 * Brigade-specific settings. The `bs.*_id` row-id references are not declared: they stay in the
 * Legacy card and are read through {@see BsReferences} only, until rules replace them.
 */
class BsSettings implements SettingsProvider
{
    public function define(SettingsBuilder $s): void
    {
        $s->section('cotisations', 'Cotisations')
            ->rule('bs', 'cotisations.tarif_reduit', 'Tarif réduit', 'membre',
                help: 'Les membres qui remplissent cette règle paient le tarif réduit');

        $s->section('comptes', 'Comptes utilisateurs')
            ->rule('bs', 'comptes.creation', 'Création automatique de compte', 'membre',
                help: 'Un compte est créé quand une attribution est enregistrée pour un membre inscrit qui remplit cette règle');
    }
}
