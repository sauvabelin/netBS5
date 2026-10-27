<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Security;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

/**
 * Entry point of the `oidc` firewall: sends an unauthenticated /oidc-login
 * visitor to the netBS login page with the `login_challenge` in its URL.
 */
final class OidcLoginEntryPoint implements AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $router,
    ) {
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->router->generate(
            'netbs.secure.login.login',
            OidcLoginChallenge::routeParameters(OidcLoginChallenge::fromRequest($request)),
        ));
    }
}
