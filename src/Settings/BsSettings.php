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
                help: 'Les membres qui remplissent ces conditions paient la cotisation réduite. Par défaut : membres ayant une fonction de poids supérieur à 99 ou faisant partie de la branche SMT.');

        $s->section('comptes', 'Comptes utilisateurs')
            ->rule('bs', 'comptes.creation', 'Création automatique de compte', 'membre',
                help: 'Quand on attribue une fonction à un membre inscrit qui remplit ces conditions, un compte netBS lui est créé. Par défaut : fonctions dont le poids atteint le minimum fixé dans les anciens paramètres, sauf pour les membres ADABS.');
    }
}
