<?php

namespace NetBS\CoreBundle\Utils\Traits;

use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

trait TokenTrait
{
    /**
     * @var TokenInterface
     */
    protected $token;

    public function setTokenStorage(TokenStorage $tokenStorage) {

        $this->token    = $tokenStorage->getToken();
    }
}
