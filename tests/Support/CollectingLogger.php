<?php

namespace App\Tests\Support;

use Psr\Log\AbstractLogger;

/** PSR logger that keeps every record, for asserting on warnings. */
class CollectingLogger extends AbstractLogger
{
    public array $records = [];

    public function log($level, $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }

    public function count(string $level): int
    {
        return count(array_filter($this->records, fn ($r) => $r['level'] === $level));
    }
}
