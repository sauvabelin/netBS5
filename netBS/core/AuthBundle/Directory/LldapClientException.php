<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Directory;

/**
 * Raised by {@see LldapClient} for transport failures, non-2xx responses and
 * GraphQL-level errors (lldap answers those with HTTP 200 + an `errors` array).
 */
class LldapClientException extends \RuntimeException
{
}
