<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version0010_rules extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rules table (conditions built from blocks); schema only, no data';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE netbs_core_rules (id INT AUTO_INCREMENT NOT NULL, context VARCHAR(64) NOT NULL, definition LONGTEXT NOT NULL, code VARCHAR(128) DEFAULT NULL, UNIQUE INDEX UNIQ_9DAE321677153098 (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE netbs_core_rules');
    }
}
