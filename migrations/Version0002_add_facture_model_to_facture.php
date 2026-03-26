<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add factureModel relation to Facture entity.
 */
final class Version0002_add_facture_model_to_facture extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add factureModel_id column to ovesco_facturation_factures';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ovesco_facturation_factures ADD factureModel_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE ovesco_facturation_factures ADD CONSTRAINT FK_668A188FBCAC9595 FOREIGN KEY (factureModel_id) REFERENCES ovesco_facturation_facture_models (id)');
        $this->addSql('CREATE INDEX IDX_668A188FBCAC9595 ON ovesco_facturation_factures (factureModel_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ovesco_facturation_factures DROP FOREIGN KEY FK_668A188FBCAC9595');
        $this->addSql('DROP INDEX IDX_668A188FBCAC9595 ON ovesco_facturation_factures');
        $this->addSql('ALTER TABLE ovesco_facturation_factures DROP factureModel_id');
    }
}
