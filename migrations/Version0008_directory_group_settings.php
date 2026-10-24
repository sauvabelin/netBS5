<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version0008_directory_group_settings extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Directory group settings on groupe types and fonctions, seeded from bs parameters';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE netbs_fichier_groupe_types ADD cree_groupe TINYINT(1) DEFAULT 0 NOT NULL, ADD sous_groupes VARCHAR(16) DEFAULT 'tous' NOT NULL");
        $this->addSql("ALTER TABLE netbs_fichier_fonctions ADD compte_dans_parent TINYINT(1) DEFAULT 0 NOT NULL");

        // One-time read of the legacy *_id parameters: unité types create a
        // directory group, CP/CL count in their parent groupe. On a fresh
        // install they are still -1, so nothing gets ticked.
        $this->addSql("UPDATE netbs_fichier_groupe_types SET cree_groupe = 1 WHERE groupeCategorie_id = (SELECT CAST(value AS SIGNED) FROM netbs_core_parameters WHERE namespace = 'bs' AND paramKey = 'groupe_categorie.unite_id')");
        $this->addSql("UPDATE netbs_fichier_fonctions SET compte_dans_parent = 1 WHERE id IN (SELECT CAST(value AS SIGNED) FROM netbs_core_parameters WHERE namespace = 'bs' AND paramKey IN ('fonction.cp_id', 'fonction.cl_id'))");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE netbs_fichier_fonctions DROP compte_dans_parent");
        $this->addSql("ALTER TABLE netbs_fichier_groupe_types DROP cree_groupe, DROP sous_groupes");
    }
}
