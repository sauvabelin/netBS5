<?php

namespace NetBS\CoreBundle\Settings;

class CoreSettings implements SettingsProvider
{
    public function define(SettingsBuilder $s): void
    {
        $s->section('application', 'Application')
            ->text('app', 'name', "Nom de l'application", default: 'netBS',
                help: 'Affiché dans le menu, les pages de connexion et les e-mails')
            ->text('app', 'organization', 'Organisation', default: 'Brigade de Sauvabelin',
                help: 'Affichée sur les pages de connexion et dans les e-mails')
            ->text('developer', 'name', 'Développeur', default: 'guillaume hochet',
                help: 'Auteur indiqué dans les métadonnées des pages');

        $s->section('recherche', 'Recherche')
            ->int('search', 'max_results', 'Résultats maximum', default: 2000, min: 1, max: 10000,
                help: "Nombre maximum de résultats affichés par une recherche");

        $s->section('galerie', 'Galerie')
            ->secret('galerie', 'parent_token', 'Mot de passe parents', important: true, minLength: 8,
                help: 'Mot de passe commun donné aux parents pour accéder aux photos');
    }
}
