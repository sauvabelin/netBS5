<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Backfill login_username for rows created after Version0006 ran.
 *
 * Several user-creation paths in the codebase did not populate the new
 * login_username column, so any BSUser inserted between Version0006 and the
 * BSUser::setUsername invariant change ended up with login_username = NULL,
 * which makes them unreachable to the firewall (provider looks up by
 * login_username).
 */
final class Version20260519090000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfill login_username from username for rows where it is NULL';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('UPDATE sauvabelin_netbs_users SET login_username = username WHERE login_username IS NULL');
    }

    public function down(Schema $schema): void
    {
        // Irreversible — we have no way to know which rows were backfilled vs. originally equal.
        $this->throwIrreversibleMigrationException('login_username backfill is not reversible');
    }
}
