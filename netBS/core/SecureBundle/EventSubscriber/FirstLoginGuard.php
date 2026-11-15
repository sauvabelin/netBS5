<?php

declare(strict_types=1);

namespace NetBS\SecureBundle\EventSubscriber;

use NetBS\SecureBundle\Mapping\BaseUser;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * An account with a temporary password ({@see BaseUser::mustChangePassword()}) may only
 * choose its own password. Every other request is sent to the first-login page, which
 * then continues to the page (or the service sign-in) the user was heading to.
 *
 * Such an account has no role and every voter denies it, so access_control refuses most
 * pages before any controller runs: those refusals are diverted here too. A remember-me
 * session is left to the firewall, which asks for the password first.
 */
final class FirstLoginGuard implements EventSubscriberInterface
{
    public const ROUTE      = 'netbs.secure.first_login';
    public const TARGET_KEY = '_netbs_first_login_target';

    /** Routes a temporary-password account may still reach. */
    private const ALLOWED_ROUTES = [
        self::ROUTE,
        'netbs.secure.login.login',
        'netbs.secure.login.logout',
        'oidc_logout',
        'oidc_error',
        '_wdt',
        '_profiler',
    ];

    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urls,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // After the router (32) and the firewall (8).
            KernelEvents::REQUEST   => ['onRequest', 0],
            // Before the firewall's exception listener (1) turns a refusal into a 403.
            KernelEvents::EXCEPTION => ['onException', 2],
        ];
    }

    public function onRequest(RequestEvent $event): void
    {
        if ($event->isMainRequest() && $this->applies($event->getRequest())) {
            $event->setResponse($this->divert($event->getRequest()));
        }
    }

    public function onException(ExceptionEvent $event): void
    {
        if ($event->isMainRequest()
            && $event->getThrowable() instanceof AccessDeniedException
            && $this->security->isGranted('IS_AUTHENTICATED_FULLY')
            && $this->applies($event->getRequest())) {
            $event->setResponse($this->divert($event->getRequest()));
        }
    }

    private function applies(Request $request): bool
    {
        $user = $this->security->getUser();

        return $user instanceof BaseUser
            && $user->mustChangePassword()
            && !in_array($request->attributes->get('_route'), self::ALLOWED_ROUTES, true);
    }

    private function divert(Request $request): Response
    {
        if (str_starts_with($request->getPathInfo(), '/api/')) {
            return new JsonResponse(['error' => 'password_change_required'], Response::HTTP_FORBIDDEN);
        }

        if ($request->isMethod('GET') && $request->hasSession()) {
            $request->getSession()->set(self::TARGET_KEY, $request->getRequestUri());
        }

        return new RedirectResponse($this->urls->generate(self::ROUTE), Response::HTTP_SEE_OTHER);
    }
}
