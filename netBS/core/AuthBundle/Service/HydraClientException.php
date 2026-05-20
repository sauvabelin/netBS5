<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Service;

/**
 * Raised by {@see HydraAdminClient} for any non-2xx response or transport
 * failure. Carries enough context (method, URL, status, response excerpt)
 * for callers to log diagnostically without re-parsing the response.
 */
final class HydraClientException extends \RuntimeException
{
    public function __construct(
        public readonly string $method,
        public readonly string $url,
        public readonly int $statusCode,
        public readonly string $responseExcerpt,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'Hydra admin call %s %s failed with status %d: %s',
                $method,
                $url,
                $statusCode,
                $responseExcerpt,
            ),
            0,
            $previous,
        );
    }
}
