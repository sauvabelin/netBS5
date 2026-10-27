<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * After a successful login on the netbs firewall, send the user back to the
 * OIDC challenge URL when an OIDC flow was the original entry point.
 *
 * The OIDC firewall stores the URL it intercepted under
 * `_security.oidc.target_path`. The netbs firewall doesn't read that key by
 * default, so the user gets bounced to the netBS dashboard instead of being
 * returned to Hydra's consent flow. This handler bridges the two by checking
 * the OIDC firewall's saved target first. A `login_challenge` in the login
 * URL wins over both: it belongs to the form that was just submitted, whereas
 * the session target may have been replaced by a sign-in in another tab.
 */
final class OidcAwareLoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    use TargetPathTrait;

    public function __construct(
        private readonly UrlGeneratorInterface $router,
        private readonly string $defaultRoute,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $session = $request->getSession();

        $loginChallenge = OidcLoginChallenge::fromRequest($request);
        if ($loginChallenge !== null) {
            $this->removeTargetPath($session, 'oidc');
            return new RedirectResponse($this->router->generate(
                'oidc_login',
                OidcLoginChallenge::routeParameters($loginChallenge),
            ));
        }

        foreach (['oidc', 'netbs'] as $firewall) {
            $target = $this->getTargetPath($session, $firewall);
            if ($target !== null && $target !== '') {
                $this->removeTargetPath($session, $firewall);
                return new RedirectResponse($target);
            }
        }

        return new RedirectResponse($this->router->generate($this->defaultRoute));
    }
}
