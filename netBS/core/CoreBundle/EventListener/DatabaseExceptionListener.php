<?php

namespace NetBS\CoreBundle\EventListener;

use Doctrine\DBAL\Exception\DriverException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
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
        $dbException = $this->findDriverExceptionInChain($event->getThrowable());

        if (!$dbException || !$this->isConstraintViolation($dbException)) {
            return;
        }

        $this->requestStack->getSession()->getFlashBag()->add('error',
            "Les données saisies ne sont pas valides. Veuillez vérifier que tous les champs obligatoires sont remplis."
        );

        $event->setResponse(new RedirectResponse(
            $this->refererOrCurrentUrl($event->getRequest()),
            Response::HTTP_SEE_OTHER
        ));
    }

    private function findDriverExceptionInChain(?\Throwable $exception): ?DriverException
    {
        for ($current = $exception; $current !== null; $current = $current->getPrevious()) {
            if ($current instanceof DriverException) {
                return $current;
            }
        }
        return null;
    }

    private function isConstraintViolation(DriverException $exception): bool
    {
        $sqlState = $exception->getSQLState();
        return $sqlState !== null && str_starts_with($sqlState, '23');
    }

    private function refererOrCurrentUrl(Request $request): string
    {
        return $request->headers->get('referer') ?: $request->getUri();
    }
}
