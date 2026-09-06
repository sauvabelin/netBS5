<?php

namespace NetBS\SecureBundle\Controller;


use NetBS\AuthBundle\Service\LoginContextProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends AbstractController
{
    #[Route('/login', name: 'netbs.secure.login.login')]
    public function loginAction(AuthenticationUtils $authenticationUtils, LoginContextProvider $loginContext)
    {
        return $this->render('@NetBSSecure/login/login.html.twig', array(
            'last_username' => $authenticationUtils->getLastUsername(),
            'error'         => $authenticationUtils->getLastAuthenticationError(),
            'oidc_client'   => $loginContext->currentClient(),
        ));
    }

    #[Route('/logout', name: 'netbs.secure.login.logout')]
    public function logoutAction()
    {
    }
}
