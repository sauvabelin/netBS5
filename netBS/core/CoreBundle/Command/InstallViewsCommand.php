<?php

namespace NetBS\CoreBundle\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Installs / refreshes the SQL views and helper tables that external services
 * (wikibs, Nextcloud user_sql, Postfix) read from this database.
 *
 * These are kept outside the Doctrine schema / migrations because they're an
 * integration contract for *other* services, not part of the netbs application
 * schema, and Doctrine has no concept of views.
 *
 * Files live in Resources/sql/views/ and are executed in alphabetical order
 * (numeric prefix encodes dependency order). Each file is expected to be
 * idempotent (CREATE OR REPLACE VIEW / CREATE TABLE IF NOT EXISTS).
 */
class InstallViewsCommand extends Command
{
    private Connection $connection;

    public function __construct(Connection $connection)
    {
        parent::__construct();
        $this->connection = $connection;
    }

    protected function configure(): void
    {
        $this
            ->setName('netbs:install-views')
            ->setDescription('(Re)install the SQL views consumed by external services');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Installing external integration views');

        $viewsDir = \dirname(__DIR__) . '/Resources/sql/views';
        if (!is_dir($viewsDir)) {
            $io->error("Views directory not found: $viewsDir");
            return Command::FAILURE;
        }

        $files = glob($viewsDir . '/*.sql');
        if ($files === false || count($files) === 0) {
            $io->warning("No .sql files in $viewsDir — nothing to do.");
            return Command::SUCCESS;
        }

        sort($files);
        foreach ($files as $file) {
            $name = basename($file);
            $io->writeln("Applying $name");
            $sql = file_get_contents($file);
            if ($sql === false) {
                $io->error("Could not read $file");
                return Command::FAILURE;
            }
            // executeStatement runs the whole string as one multi-statement
            // batch via the underlying driver; CREATE VIEW / CREATE TABLE
            // don't return rows so executeStatement is the right call.
            $this->connection->executeStatement($sql);
        }

        $io->success(sprintf('Installed %d view file(s).', count($files)));
        return Command::SUCCESS;
    }
}
