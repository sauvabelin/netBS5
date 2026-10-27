<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Security;

use Symfony\Component\HttpFoundation\Request;

/**
 * The Hydra `login_challenge` carried in the URL of /oidc-login and of the
 * netBS login page.
 *
 * Keeping it in the login URL (not only in the session's OIDC target path)
 * means a reload, or a retry after the session expired with the form open,
 * still returns the user to the client that asked for the sign-in instead of
 * the netBS dashboard.
 */
final class OidcLoginChallenge
{
    public const PARAMETER = 'login_challenge';

    /** Hydra challenges are opaque URL-safe base64; anything else is not ours. */
    private const PATTERN = '/^[A-Za-z0-9_\-=.~]{1,8192}$/';

    public static function fromRequest(Request $request): ?string
    {
        $challenge = $request->query->get(self::PARAMETER);

        return \is_string($challenge) && preg_match(self::PATTERN, $challenge) === 1 ? $challenge : null;
    }

    /**
     * Route parameters for the login page: the challenge when there is one.
     *
     * @return array<string, string>
     */
    public static function routeParameters(?string $challenge): array
    {
        return $challenge === null ? [] : [self::PARAMETER => $challenge];
    }
}
