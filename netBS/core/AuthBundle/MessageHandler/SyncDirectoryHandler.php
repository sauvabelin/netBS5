<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\MessageHandler;

use NetBS\AuthBundle\Directory\DirectoryNotConfiguredException;
use NetBS\AuthBundle\Directory\DirectorySyncRunner;
use NetBS\AuthBundle\Directory\LldapClientException;
use NetBS\AuthBundle\Message\SyncDirectory;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class SyncDirectoryHandler
{
    public function __construct(
        private readonly DirectorySyncRunner $runner,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Failures are logged, not rethrown: Messenger retries would only repeat
     * what the next scheduled sync does anyway.
     */
    public function __invoke(SyncDirectory $message): void
    {
        try {
            $report = $this->runner->run($message->requestedAt);
        } catch (DirectoryNotConfiguredException|LldapClientException $e) {
            $this->logger->error('directory.sync_failed', ['exception' => $e]);
            return;
        }

        if ($report?->errors) {
            $this->logger->warning('directory.sync_errors', ['errors' => $report->errors]);
        }
    }
}
