<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version0012_directory_rules extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'LDAP publication rules and computed groups; drops groupe type directory columns (settings not converted)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE netbs_auth_directory_computed_groups (id INT AUTO_INCREMENT NOT NULL, membres_rule_id INT NOT NULL, name VARCHAR(255) NOT NULL, description VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_155FC5B75E237E06 (name), UNIQUE INDEX UNIQ_155FC5B7D1A17BA8 (membres_rule_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE netbs_auth_directory_publication_rules (id INT AUTO_INCREMENT NOT NULL, selection_rule_id INT NOT NULL, sous_groupes_rule_id INT DEFAULT NULL, position INT NOT NULL, label VARCHAR(255) NOT NULL, sous_groupes VARCHAR(16) NOT NULL, UNIQUE INDEX UNIQ_E741ECBEDCE9A5B0 (selection_rule_id), UNIQUE INDEX UNIQ_E741ECBE3910D587 (sous_groupes_rule_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE netbs_auth_directory_computed_groups ADD CONSTRAINT FK_155FC5B7D1A17BA8 FOREIGN KEY (membres_rule_id) REFERENCES netbs_core_rules (id)');
        $this->addSql('ALTER TABLE netbs_auth_directory_publication_rules ADD CONSTRAINT FK_E741ECBEDCE9A5B0 FOREIGN KEY (selection_rule_id) REFERENCES netbs_core_rules (id)');
        $this->addSql('ALTER TABLE netbs_auth_directory_publication_rules ADD CONSTRAINT FK_E741ECBE3910D587 FOREIGN KEY (sous_groupes_rule_id) REFERENCES netbs_core_rules (id) ON DELETE SET NULL');
        // The per-type directory settings are dropped, not converted: until an
        // admin adds rules, the sync reports « not configured » and leaves lldap alone.
        // Once the first rule or computed group is saved, the sync deletes every id:* group
        // that rule does not select, and re-creates groups with new ids as rules are added.
        // That churn is accepted on purpose: when this migration was written, nothing
        // consumed the lldap groups yet. Convert the old flags into rules instead if that
        // is no longer true when this is deployed.
        $this->addSql('ALTER TABLE netbs_fichier_groupe_types DROP cree_groupe, DROP sous_groupes');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE netbs_auth_directory_computed_groups DROP FOREIGN KEY FK_155FC5B7D1A17BA8');
        $this->addSql('ALTER TABLE netbs_auth_directory_publication_rules DROP FOREIGN KEY FK_E741ECBEDCE9A5B0');
        $this->addSql('ALTER TABLE netbs_auth_directory_publication_rules DROP FOREIGN KEY FK_E741ECBE3910D587');
        $this->addSql('DROP TABLE netbs_auth_directory_computed_groups');
        $this->addSql('DROP TABLE netbs_auth_directory_publication_rules');
        // As Version0008 left them, without their values
        $this->addSql('ALTER TABLE netbs_fichier_groupe_types ADD cree_groupe TINYINT(1) DEFAULT 0 NOT NULL, ADD sous_groupes VARCHAR(16) DEFAULT \'tous\' NOT NULL');
    }
}
