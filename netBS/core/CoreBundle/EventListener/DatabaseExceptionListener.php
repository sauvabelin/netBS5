<?php

namespace NetBS\CoreBundle\EventListener;

use Doctrine\DBAL\Exception\DriverException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Catches database constraint violations (NOT NULL, UNIQUE, FK) and
 * redirects back with a flash error instead of showing a 500 page.
 */
class DatabaseExceptionListener
{
    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        // Walk the exception chain to find a DriverException
        $dbException = null;
        $current = $exception;
        while ($current !== null) {
            if ($current instanceof DriverException) {
                $dbException = $current;
                break;
            }
            $current = $current->getPrevious();
        }

        if (!$dbException) {
            return;
        }

        $sqlState = $dbException->getSQLState();

        // Only handle constraint violations (SQLSTATE 23xxx)
        if (!$sqlState || !str_starts_with($sqlState, '23')) {
            return;
        }

        $request = $event->getRequest();
        $session = $this->requestStack->getSession();

        $session->getFlashBag()->add('error',
            "Les données saisies ne sont pas valides. Veuillez vérifier que tous les champs obligatoires sont remplis."
        );

        // Redirect back to the referring page, or to the same URL
        $referer = $request->headers->get('referer');
        $redirectUrl = $referer ?: $request->getUri();

        $event->setResponse(new RedirectResponse($redirectUrl, Response::HTTP_SEE_OTHER));
    }
}
