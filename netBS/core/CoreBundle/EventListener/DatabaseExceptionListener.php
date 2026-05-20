<?php

namespace NetBS\CoreBundle\EventListener;

use Doctrine\DBAL\Exception\DriverException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Safety net for uncaught DB constraint violations (SQLSTATE 23xxx). Returns
 * 422 — never redirects, so the browser keeps the user's form data in history.
 * Controllers should prefer HandlesFormPersistenceTrait for forms they own.
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

        $session = $this->requestStack->getSession();
        if (method_exists($session, 'getFlashBag')) {
            $session->getFlashBag()->add('error',
                "Une contrainte de base de données a empêché l'enregistrement. " .
                "Si le problème persiste, contactez un administrateur."
            );
        }

        // 422 so Turbo re-renders the response body in place instead of navigating.
        $event->setResponse(new Response(
            $this->renderErrorBody($dbException->getMessage()),
            Response::HTTP_UNPROCESSABLE_ENTITY,
            ['Content-Type' => 'text/html; charset=utf-8'],
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

    private function renderErrorBody(string $detail): string
    {
        $escaped = htmlspecialchars($detail, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return <<<HTML
            <!doctype html>
            <html lang="fr">
            <head><meta charset="utf-8"><title>Erreur de base de données</title></head>
            <body>
                <h1>Erreur de base de données</h1>
                <p>Une contrainte de base de données a empêché l'enregistrement.
                   Cliquez sur le bouton « précédent » de votre navigateur pour
                   retrouver le formulaire et corriger les valeurs.</p>
                <pre style="white-space:pre-wrap">{$escaped}</pre>
            </body>
            </html>
            HTML;
    }
}
