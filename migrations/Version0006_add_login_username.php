<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version0006_add_login_username extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add mutable login_username column to BSUser and backfill from username';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sauvabelin_netbs_users ADD login_username VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE sauvabelin_netbs_users SET login_username = username WHERE login_username IS NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_login_username ON sauvabelin_netbs_users (login_username)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_login_username ON sauvabelin_netbs_users');
        $this->addSql('ALTER TABLE sauvabelin_netbs_users DROP login_username');
    }
}
