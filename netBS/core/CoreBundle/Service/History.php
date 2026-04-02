<?php

namespace NetBS\CoreBundle\Service;

use NetBS\CoreBundle\Model\RouteHistory;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\RouterInterface;

class History
{
    const SESSION_KEY   = 'netbs.history';

    /**
     * @var RequestStack
     */
    protected $requestStack;

    /**
     * @var RouterInterface
     */
    protected $router;

    /**
     * @var bool
     */
    protected $updated  = false;

    public function __construct(RequestStack $requestStack, RouterInterface $router)
    {
        $this->requestStack = $requestStack;
        $this->router   = $router;
    }

    public function getHistory() {

        $data       = $this->requestStack->getSession()->get(self::SESSION_KEY);

        if(is_null($data))
            return [];
        else
            return unserialize($data);
    }

    public function update() {

        $request = $this->requestStack->getCurrentRequest();

        if($request->get('_route') == '_wdt' || $request->isXmlHttpRequest() || $this->updated)
            return;

        $route          = new RouteHistory($request->get('_route'), $request->attributes->get('_route_params'));
        $history        = $this->getHistory();
        $history[]      = $route;
        $this->updated  = true;

        $this->requestStack->getSession()->set(self::SESSION_KEY, serialize($history));
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
