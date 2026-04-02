<?php

namespace NetBS\CoreBundle\Utils\Traits;

use Symfony\Component\HttpFoundation\RequestStack;

trait SessionTrait
{
    /**
     * @var RequestStack
     */
    protected $requestStack;

    public function setRequestStack(RequestStack $requestStack) {

        $this->requestStack = $requestStack;
    }

    protected function getSession() {
        return $this->requestStack->getSession();
    }
}
