<?php

namespace App\Listener;

use App\Entity\BSUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ForceNewPasswordListener
{
    private $router;

    private $storage;

    private $session;

    public function __construct(TokenStorageInterface $storage, RouterInterface $router, SessionInterface $session)
    {
        $this->storage  = $storage;
        $this->router   = $router;
        $this->session  = $session;
    }

    public function verifyUser(RequestEvent $event) {

        /** @var BSUser $user */
        if(!$this->storage->getToken())
            return;

        $user   = $this->storage->getToken()->getUser();

        if(!$user instanceof BSUser)
            return;

        if($user->hasRole('ROLE_ADMIN'))
            return;

        if($user->isNewPasswordRequired()
            && $event->getRequest()->getRequestUri() !== $this->router->generate('netbs.secure.user.account_page')
            && $event->getRequestType() === 1) {

            if($user->hasRole("ROLE_ADMIN")) {
                $this->session->getFlashBag()->add('warning',
                    "T'es admin mais pense à changer de mot de passe!");
                return;
            }

            $this->session->getFlashBag()->add('info', "Avant de pouvoir continuer, veuillez changer de mot de passe.");
            $event->setResponse(new RedirectResponse($this->router->generate('netbs.secure.user.account_page')));
        }
    }
}
