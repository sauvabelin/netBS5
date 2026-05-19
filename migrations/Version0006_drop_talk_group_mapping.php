<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Drop bs_talk_group_mapping: the Nextcloud Talk group-membership sync used
 * to be pushed from netbs to Nextcloud via Symfony Messenger, with this
 * table as a local dedup cache. That sync is now handled directly by the
 * Nextcloud user_sql Talk app (which reads the nextcloud_user_groups view
 * installed by netbs:install-views). The producer code, handler, and
 * Talk-related Doctrine subscribers have been removed in the same commit;
 * this migration drops the now-dead table.
 */
final class Version0006_drop_talk_group_mapping extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop bs_talk_group_mapping (Talk sync now owned by Nextcloud user_sql app)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS bs_talk_group_mapping');
    }

    public function down(Schema $schema): void
    {
        $this->addSql("CREATE TABLE bs_talk_group_mapping (id INT AUTO_INCREMENT NOT NULL, username VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, group_name VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = ''");
    }
}
