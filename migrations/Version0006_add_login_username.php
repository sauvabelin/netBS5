<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add a mutable login_username column to BSUser.
 *
 * The column is created NOT NULL with a unique index. We backfill from
 * `username` (the immutable OIDC subject) before tightening the constraint so
 * existing rows survive.
 *
 * The column is NOT NULL because MariaDB's unique index allows multiple NULL
 * values — any insert path that forgot to populate login_username would
 * silently accumulate NULL rows, and findOneBy(['loginUsername' => null])
 * would return whichever the storage engine happened to surface first.
 */
final class Version0006_add_login_username extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add mutable login_username column to BSUser (NOT NULL, unique, backfilled from username)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sauvabelin_netbs_users ADD login_username VARCHAR(255) DEFAULT NULL');
        $this->addSql('UPDATE sauvabelin_netbs_users SET login_username = username WHERE login_username IS NULL');
        $this->addSql('ALTER TABLE sauvabelin_netbs_users MODIFY login_username VARCHAR(255) NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_login_username ON sauvabelin_netbs_users (login_username)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_login_username ON sauvabelin_netbs_users');
        $this->addSql('ALTER TABLE sauvabelin_netbs_users DROP login_username');
    }
}
