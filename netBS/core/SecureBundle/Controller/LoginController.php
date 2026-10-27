<?php

namespace NetBS\SecureBundle\Controller;


use NetBS\AuthBundle\Security\OidcLoginChallenge;
use NetBS\AuthBundle\Service\LoginContextProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginController extends AbstractController
{
    use TargetPathTrait;

    #[Route('/login', name: 'netbs.secure.login.login')]
    public function loginAction(Request $request, AuthenticationUtils $authenticationUtils, LoginContextProvider $loginContext)
    {
        // An OIDC sign-in carries its challenge in the URL; restore the OIDC
        // return target from it in case the session was lost (expired, or a
        // retry after "Invalid CSRF token") so the login still goes back to
        // the requesting application.
        $loginChallenge = OidcLoginChallenge::fromRequest($request);
        if ($loginChallenge !== null) {
            $this->saveTargetPath($request->getSession(), 'oidc', $this->generateUrl(
                'oidc_login',
                OidcLoginChallenge::routeParameters($loginChallenge),
            ));
        }

        return $this->render('@NetBSSecure/login/login.html.twig', array(
            'last_username'   => $authenticationUtils->getLastUsername(),
            'error'           => $authenticationUtils->getLastAuthenticationError(),
            'oidc_client'     => $loginContext->currentClient(),
            'login_challenge' => $loginChallenge,
        ));
    }

    #[Route('/logout', name: 'netbs.secure.login.logout')]
    public function logoutAction()
    {
    }
}
