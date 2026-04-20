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
        $route   = $request->get('_route');

        if ($this->updated || $this->shouldSkipForNavigationHistory($request, $route)) {
            return;
        }

        $entry         = new RouteHistory($route, $request->attributes->get('_route_params'));
        $history       = $this->getHistory();
        $history[]     = $entry;
        $this->updated = true;

        $this->requestStack->getSession()->set(self::SESSION_KEY, serialize($history));
    }

    private function shouldSkipForNavigationHistory(\Symfony\Component\HttpFoundation\Request $request, ?string $route): bool
    {
        if ($route === '_wdt' || $request->isXmlHttpRequest()) {
            return true;
        }
        if (!$route) {
            return true;
        }
        if ($request->headers->get('Accept') === 'application/json') {
            return true;
        }
        return $this->isDataFetchingRoute($route);
    }

    private function isDataFetchingRoute(string $route): bool
    {
        if (str_starts_with($route, 'api')) {
            return true;
        }
        foreach (['ajax', 'modal', 'select2', 'search', 'xeditable', 'helper', 'preview'] as $fragment) {
            if (str_contains($route, $fragment)) {
                return true;
            }
        }
        return false;
    }

    public function getPreviousRoute($previousness = 2) {

        $route  = $this->goToHistory($previousness);
        if ($route === null || !$route->getRouteName()) {
            return new RedirectResponse($this->router->generate('netbs.core.home.dashboard'));
        }
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
