<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version0003_soft_delete_and_audit_log extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add soft delete (deletedAt) to core entities and create audit log table';
    }

    public function up(Schema $schema): void
    {
        // Soft delete columns (IF NOT EXISTS for idempotency)
        $tables = [
            'netbs_fichier_adresses',
            'netbs_fichier_attributions',
            'netbs_fichier_contact_informations',
            'netbs_fichier_emails',
            'netbs_fichier_familles',
            'netbs_fichier_geniteurs',
            'netbs_fichier_groupes',
            'netbs_fichier_membres',
            'netbs_fichier_obtentions_distinction',
            'netbs_fichier_telephones',
            'netbs_secure_users',
            'sauvabelin_netbs_groupes',
            'sauvabelin_netbs_membres',
            'sauvabelin_netbs_users',
        ];

        foreach ($tables as $table) {
            $this->addSql("ALTER TABLE $table ADD COLUMN IF NOT EXISTS deletedAt DATETIME DEFAULT NULL");
        }

        // Audit log table
        $this->addSql('CREATE TABLE IF NOT EXISTS netbs_core_audit_log (
            id INT AUTO_INCREMENT NOT NULL,
            entity_class VARCHAR(255) NOT NULL,
            entity_id INT NOT NULL,
            action VARCHAR(20) NOT NULL,
            property VARCHAR(255) DEFAULT NULL,
            old_value LONGTEXT DEFAULT NULL,
            new_value LONGTEXT DEFAULT NULL,
            display_name VARCHAR(255) NOT NULL,
            user_id INT DEFAULT NULL,
            createdAt DATETIME NOT NULL,
            updatedAt DATETIME NOT NULL,
            INDEX IDX_4DB31AA4A76ED395 (user_id),
            PRIMARY KEY(id),
            CONSTRAINT FK_4DB31AA4A76ED395 FOREIGN KEY (user_id) REFERENCES sauvabelin_netbs_users (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE netbs_core_audit_log');
        $this->addSql('ALTER TABLE netbs_fichier_attributions DROP deletedAt');
        $this->addSql('ALTER TABLE netbs_fichier_membres DROP deletedAt');
        $this->addSql('ALTER TABLE netbs_fichier_obtentions_distinction DROP deletedAt');
        $this->addSql('ALTER TABLE netbs_fichier_adresses DROP deletedAt');
        $this->addSql('ALTER TABLE netbs_fichier_familles DROP deletedAt');
        $this->addSql('ALTER TABLE netbs_fichier_telephones DROP deletedAt');
        $this->addSql('ALTER TABLE netbs_fichier_geniteurs DROP deletedAt');
        $this->addSql('ALTER TABLE netbs_fichier_contact_informations DROP deletedAt');
        $this->addSql('ALTER TABLE netbs_fichier_groupes DROP deletedAt');
        $this->addSql('ALTER TABLE netbs_fichier_emails DROP deletedAt');
        $this->addSql('ALTER TABLE netbs_secure_users DROP deletedAt');
        $this->addSql('ALTER TABLE sauvabelin_netbs_membres DROP deletedAt');
        $this->addSql('ALTER TABLE sauvabelin_netbs_users DROP deletedAt');
        $this->addSql('ALTER TABLE sauvabelin_netbs_groupes DROP deletedAt');
    }
}
