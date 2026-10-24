<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Security;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the netBS session from the /oidc-* routes.
 *
 * Those routes sit on the `oidc` firewall, which has `logout: false`, so
 * `Security::logout()` there only drops the token: it neither invalidates the
 * session nor clears the `netbs` firewall's remember-me cookie, and the user
 * is signed straight back in on the next /netBS hit. This does both.
 */
final class LocalSessionTerminator
{
    public function __construct(
        private readonly Security $security,
        // Must match `firewalls.netbs.remember_me` in config/packages/security.yaml.
        private readonly string $rememberMeCookieName = 'REMEMBERME',
        private readonly string $rememberMeCookiePath = '/netBS',
    ) {
    }

    /**
     * Logs the current user out (if any) and invalidates the session. Callers
     * must pass the response they return to {@see clearRememberMe()}.
     */
    public function terminate(Request $request): void
    {
        if ($this->security->getUser() !== null) {
            $this->security->logout(validateCsrfToken: false);
        }
        if ($request->hasSession()) {
            $request->getSession()->invalidate();
        }
    }

    public function clearRememberMe(Response $response): Response
    {
        $response->headers->clearCookie($this->rememberMeCookieName, $this->rememberMeCookiePath);
        return $response;
    }
}
