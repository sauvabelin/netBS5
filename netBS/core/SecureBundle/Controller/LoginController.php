<?php

namespace NetBS\SecureBundle\Controller;


use NetBS\AuthBundle\Security\OidcLoginChallenge;
use NetBS\AuthBundle\Service\LoginContextProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\AuthenticationServiceException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends AbstractController
{
    #[Route('/login', name: 'netbs.secure.login.login')]
    public function loginAction(Request $request, AuthenticationUtils $authenticationUtils, LoginContextProvider $loginContext)
    {
        // An OIDC sign-in carries its challenge in the URL. The form posts it
        // back, so the login returns to the requesting application even if
        // the session was lost meanwhile (expired, "Invalid CSRF token" retry).
        $loginChallenge = OidcLoginChallenge::fromRequest($request);

        return $this->render('@NetBSSecure/login/login.html.twig', array(
            'last_username' => $authenticationUtils->getLastUsername(),
            'error'         => self::errorMessage($authenticationUtils->getLastAuthenticationError()),
            'oidc_client'   => $loginContext->currentClient($loginChallenge),
            'login_params'  => OidcLoginChallenge::routeParameters($loginChallenge),
        ));
    }

    /**
     * The failed login, in French: Symfony's own messages are English keys (no translator here).
     * An unknown username comes in as BadCredentialsException too, so the two stay indistinguishable.
     */
    private static function errorMessage(?AuthenticationException $error): ?string
    {
        return match (true) {
            $error === null                                                => null,
            $error instanceof BadCredentialsException                      => "Nom d'utilisateur ou mot de passe incorrect.",
            $error instanceof InvalidCsrfTokenException                    => 'La page est restée ouverte trop longtemps. Réessaie.',
            $error instanceof TooManyLoginAttemptsAuthenticationException  => 'Trop de tentatives. Réessaie dans quelques minutes.',
            $error instanceof AccountStatusException                       => 'Ce compte est désactivé. Contacte un administrateur.',
            $error instanceof AuthenticationServiceException               => 'La connexion est momentanément impossible. Réessaie dans quelques minutes.',
            default                                                        => 'La connexion a échoué. Réessaie.',
        };
    }

    #[Route('/logout', name: 'netbs.secure.login.logout')]
    public function logoutAction()
    {
    }
}
