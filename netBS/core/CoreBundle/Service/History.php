<?php

namespace NetBS\CoreBundle\Service;

use NetBS\CoreBundle\Model\RouteHistory;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\RouterInterface;

class History
{
    const SESSION_KEY   = 'netbs.history';

    /**
     * @var null|\Symfony\Component\HttpFoundation\Request
     */
    protected $request;

    /**
     * @var SessionInterface
     */
    protected $session;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var bool
     */
    protected $updated  = false;

    public function __construct(SessionInterface $session, RequestStack $stack, RouterInterface $router)
    {
        $this->session  = $session;
        $this->router   = $router;
        $this->request  = $stack->getCurrentRequest();
    }

    public function getHistory() {

        $data       = $this->session->get(self::SESSION_KEY);

        if(is_null($data))
            return [];
        else
            return unserialize($data);
    }

    public function update() {

        if($this->request->get('_route') == '_wdt' || $this->request->isXmlHttpRequest() || $this->updated)
            return;

        $route          = new RouteHistory($this->request->get('_route'), $this->request->attributes->get('_route_params'));
        $history        = $this->getHistory();
        $history[]      = $route;
        $this->updated  = true;

        $this->session->set(self::SESSION_KEY, serialize($history));
    }

    public function getPreviousRoute($previousness = 2) {

        $route  = $this->goToHistory($previousness);
        return new RedirectResponse($this->router->generate($route->getRouteName(), $route->getParams()));
    }

    /**
     * @param $previousness
     * @return RouteHistory|null
     */
    public function goToHistory($previousness) {

        $history    = $this->getHistory();

        if(count($history) > $previousness)
            return $history[count($history) - $previousness];

        return null;
    }
}
