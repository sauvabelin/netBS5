<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version0007_password_reset extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add password_changed_at to BSUser and create sauvabelin_netbs_reset_password_request table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE sauvabelin_netbs_users ADD password_changed_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");

        $this->addSql("CREATE TABLE sauvabelin_netbs_reset_password_request (id INT AUTO_INCREMENT NOT NULL, user_id INT NOT NULL, selector VARCHAR(20) NOT NULL, hashedToken VARCHAR(100) NOT NULL, requestedAt DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', expiresAt DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_5A3E80BDA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");

        $this->addSql("ALTER TABLE sauvabelin_netbs_reset_password_request ADD CONSTRAINT FK_5A3E80BDA76ED395 FOREIGN KEY (user_id) REFERENCES sauvabelin_netbs_users (id) ON DELETE CASCADE");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("ALTER TABLE sauvabelin_netbs_reset_password_request DROP FOREIGN KEY FK_5A3E80BDA76ED395");
        $this->addSql("DROP TABLE sauvabelin_netbs_reset_password_request");
        $this->addSql("ALTER TABLE sauvabelin_netbs_users DROP password_changed_at");
    }
}
