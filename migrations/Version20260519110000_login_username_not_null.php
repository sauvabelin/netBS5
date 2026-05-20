<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Tighten login_username to NOT NULL.
 *
 * Version0006 added login_username as nullable with a unique index. MariaDB's
 * unique index allows multiple NULL values, so any insert path that forgets to
 * populate login_username silently accumulates NULL rows — and
 * findOneBy(['loginUsername' => null]) returns whichever NULL row the storage
 * engine happens to surface first. To make the column safe as a login lookup
 * key, enforce NOT NULL at the schema level.
 *
 * The UPDATE here is a safety net: by the time this migration runs, V0006
 * should have backfilled every row, but the column has been nullable in
 * production long enough that fresh inserts (between V0006 and the
 * BSUser::setLoginUsername / setUsername invariant changes on this branch)
 * may have left additional NULLs. Doing the backfill again is idempotent.
 */
final class Version20260519110000_login_username_not_null extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Backfill any remaining NULL login_username values and tighten the column to NOT NULL';
    }

    public function up(Schema $schema): void
    {
        // Safety-net backfill — idempotent, no-op if V0006 already covered every row.
        $this->addSql('UPDATE sauvabelin_netbs_users SET login_username = username WHERE login_username IS NULL');
        $this->addSql('ALTER TABLE sauvabelin_netbs_users MODIFY login_username VARCHAR(255) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE sauvabelin_netbs_users MODIFY login_username VARCHAR(255) DEFAULT NULL');
    }
}
