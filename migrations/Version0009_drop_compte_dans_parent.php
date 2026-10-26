<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version0009_drop_compte_dans_parent extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop the fonction "compte dans le groupe parent" flag and the "fonctions" sous-groupes mode';
    }

    public function up(Schema $schema): void
    {
        // Without the flag the mode would only keep direct attributions.
        $this->addSql("UPDATE netbs_fichier_groupe_types SET sous_groupes = 'direct' WHERE sous_groupes = 'fonctions'");
        $this->addSql("ALTER TABLE netbs_fichier_fonctions DROP compte_dans_parent");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE netbs_fichier_fonctions ADD compte_dans_parent TINYINT(1) DEFAULT 0 NOT NULL");
    }
}
