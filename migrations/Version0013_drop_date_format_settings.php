<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version0013_drop_date_format_settings extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Drop the date format settings: the format is fixed in code (DateFormat)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DELETE FROM netbs_core_parameters WHERE namespace = 'format'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql("INSERT INTO netbs_core_parameters (namespace, paramKey, value) VALUES
            ('format', 'php_date', 'd.m.Y'),
            ('format', 'js_date', 'dd.mm.yyyy'),
            ('format', 'js_moment_date', 'DD.MM.YYYY'),
            ('format', 'php_datetime', 'd.m.Y H\\\\hi')");
    }
}
