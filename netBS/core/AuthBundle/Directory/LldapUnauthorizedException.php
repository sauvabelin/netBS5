<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Directory;

/**
 * lldap answered 401 to an authenticated call: the cached JWT expired.
 */
final class LldapUnauthorizedException extends LldapClientException
{
}
