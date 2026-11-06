<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version0011_facture_model_rule extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Facture models: rule (one-to-one to netbs_core_rules) and texts format marker; schema only, existing models keep their expression and old-format texts';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ovesco_facturation_facture_models ADD rule_id INT DEFAULT NULL, ADD textes_format SMALLINT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE ovesco_facturation_facture_models ADD CONSTRAINT FK_9D03F862744E0351 FOREIGN KEY (rule_id) REFERENCES netbs_core_rules (id) ON DELETE SET NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_9D03F862744E0351 ON ovesco_facturation_facture_models (rule_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE ovesco_facturation_facture_models DROP FOREIGN KEY FK_9D03F862744E0351');
        $this->addSql('DROP INDEX UNIQ_9D03F862744E0351 ON ovesco_facturation_facture_models');
        $this->addSql('ALTER TABLE ovesco_facturation_facture_models DROP rule_id, DROP textes_format');
    }
}
