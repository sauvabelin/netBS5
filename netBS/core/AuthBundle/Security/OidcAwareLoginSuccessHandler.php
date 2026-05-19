<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

/**
 * After a successful login on the netbs firewall, send the user back to the
 * OIDC challenge URL when an OIDC flow was the original entry point.
 *
 * The OIDC firewall stores the URL it intercepted under
 * `_security.oidc.target_path`. The netbs firewall doesn't read that key by
 * default, so the user gets bounced to the netBS dashboard instead of being
 * returned to Hydra's consent flow. This handler bridges the two by checking
 * the OIDC firewall's saved target first.
 */
final class OidcAwareLoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $router,
        private readonly string $defaultRoute,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $session = $request->getSession();

        foreach (['_security.oidc.target_path', '_security.netbs.target_path'] as $key) {
            $target = $session->get($key);
            if (is_string($target) && $target !== '') {
                $session->remove($key);
                return new RedirectResponse($target);
            }
        }

        return new RedirectResponse($this->router->generate($this->defaultRoute));
    }
}
