<?php

namespace NetBS\SecureBundle\Controller;


use NetBS\AuthBundle\Security\OidcLoginChallenge;
use NetBS\AuthBundle\Service\LoginContextProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
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
            'error'         => $authenticationUtils->getLastAuthenticationError(),
            'oidc_client'   => $loginContext->currentClient($loginChallenge),
            'login_params'  => OidcLoginChallenge::routeParameters($loginChallenge),
        ));
    }

    #[Route('/logout', name: 'netbs.secure.login.logout')]
    public function logoutAction()
    {
    }
}
