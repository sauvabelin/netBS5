<?php

namespace App\Tests\Support;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Lets a test start from an empty netbs_core_parameters (a fresh install) and puts the
 * shared test database back as it was afterwards.
 */
trait ParametersSnapshotTrait
{
    private ?array $parametersSnapshot = null;

    protected function emptyParameters(EntityManagerInterface $em): void
    {
        $connection               = $em->getConnection();
        $this->parametersSnapshot = $connection->fetchAllAssociative('SELECT * FROM netbs_core_parameters');
        $connection->executeStatement('DELETE FROM netbs_core_parameters');
        static::getContainer()->get('cache.app')->clear();
    }

    protected function restoreParameters(EntityManagerInterface $em): void
    {
        if ($this->parametersSnapshot === null) {
            return;
        }

        $connection = $em->getConnection();
        $connection->executeStatement('DELETE FROM netbs_core_parameters');
        foreach ($this->parametersSnapshot as $row) {
            $connection->insert('netbs_core_parameters', $row);
        }
        static::getContainer()->get('cache.app')->clear();
        $this->parametersSnapshot = null;
    }
}
