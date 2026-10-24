<?php

declare(strict_types=1);

namespace App\Ldap;

/**
 * Raised by {@see LldapClient} for transport failures, non-2xx responses and
 * GraphQL-level errors (lldap answers those with HTTP 200 + an `errors` array).
 */
final class LldapClientException extends \RuntimeException
{
}
