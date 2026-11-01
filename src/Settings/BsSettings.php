<?php

namespace App\Settings;

use NetBS\CoreBundle\Settings\SettingsBuilder;
use NetBS\CoreBundle\Settings\SettingsProvider;

/**
 * Brigade-specific references read by netBS code, stored as row ids in netbs_core_parameters.
 * Keys read only by the Nextcloud SQL views (02, 05) are not declared: they stay in the Legacy card,
 * as do groupe.branche_smt_id and fonction.weight.user_account, read raw by the fallbacks of the
 * cotisations and account-creation rules until those rules are set.
 */
class BsSettings implements SettingsProvider
{
    public function define(SettingsBuilder $s): void
    {
        $s->section('brigade', 'Brigade')
            ->entity('bs', 'groupe.adabs_id', 'Groupe ADABS', 'groupe', important: true,
                help: 'Membres non inscrits (CDC, cotisations, Nextcloud « tous »), sans compte; attribution gardée à la désinscription')
            ->entity('bs', 'groupe.apmbs_id', 'Groupe APMBS', 'groupe', important: true,
                help: 'Membres exclus par le filtre « sans APMBS » des recherches')
            ->entity('bs', 'groupe.branche_eclaireurs_id', 'Branche éclaireurs', 'groupe', important: true,
                help: 'Liste automatique « gars qui bougent »')
            ->entity('bs', 'groupe.branche_eclaireuses_id', 'Branche éclaireuses', 'groupe', important: true,
                help: 'Liste automatique « gars qui bougent »')
            ->entity('bs', 'groupe.branche_louveteaux_id', 'Branche louveteaux', 'groupe', important: true,
                help: 'Liste automatique « gars qui bougent »')
            ->entity('bs', 'groupe.branche_louvettes_id', 'Branche louvettes', 'groupe', important: true,
                help: 'Liste automatique « gars qui bougent »')

            ->entity('bs', 'fonction.cp_id', 'Fonction chef de patrouille', 'fonction', important: true,
                help: "Liste automatique « test d'entrée »; groupes Nextcloud")
            ->entity('bs', 'fonction.cl_id', 'Fonction CL', 'fonction', important: true,
                help: "Liste automatique « test d'entrée »; groupes Nextcloud")
            ->entity('bs', 'fonction.rouge_id', 'Fonction routier', 'fonction', important: true,
                help: "Liste automatique « test d'entrée »")
            ->entity('bs', 'fonction.louveteau_id', 'Fonction louveteau', 'fonction', important: true,
                help: 'Liste automatique « gars qui bougent »')
            ->entity('bs', 'fonction.eclaireur_id', 'Fonction éclaireur', 'fonction', important: true,
                help: 'Liste automatique « gars qui bougent »')

            ->entity('bs', 'groupe_categorie.unite_id', 'Catégorie « unité »', 'groupe_categorie', important: true,
                help: 'Pages de groupe')
            ->entity('bs', 'groupe_type.branche_id', 'Type « branche »', 'groupe_type', important: true,
                help: 'Pages de groupe, factures, groupes Nextcloud');

        $s->section('cotisations', 'Cotisations')
            ->rule('bs', 'cotisations.tarif_reduit', 'Tarif réduit', 'membre',
                help: 'Les membres qui remplissent cette règle paient le tarif réduit');

        $s->section('comptes', 'Comptes utilisateurs')
            ->rule('bs', 'comptes.creation', 'Création automatique de compte', 'membre',
                help: 'Un compte est créé quand une attribution est enregistrée pour un membre inscrit qui remplit cette règle');
    }
}
