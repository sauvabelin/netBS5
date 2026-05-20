<?php

namespace NetBS\CoreBundle\EventListener;

use Doctrine\DBAL\Exception\DriverException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Last-resort safety net for uncaught DB constraint violations (SQLSTATE 23xxx —
 * NOT NULL, UNIQUE, FK). Surfaces a friendly toast message and a 422 response
 * instead of a raw 500. Crucially: this listener NEVER redirects, so the
 * browser stays on the same URL and the user's form data is preserved in the
 * native history (back button restores fields).
 *
 * Controllers that handle a specific form's known constraint violations
 * (e.g. uniqueness conflicts on user-edited fields) should catch the
 * Doctrine\DBAL\Exception\* subclasses themselves and re-render the form
 * with a form-level error — this listener is for the violations no controller
 * was prepared for.
 *
 * See {@see \NetBS\CoreBundle\Controller\Trait\HandlesFormPersistenceTrait}
 * for the controller-side helper.
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

        // Flash a friendly toast for the next page render. We intentionally do
        // NOT redirect — redirecting destroys the user's POST data and is
        // useless given the response will be a 422 the browser will not
        // navigate to a new URL for.
        $session = $this->requestStack->getSession();
        if (method_exists($session, 'getFlashBag')) {
            $session->getFlashBag()->add('error',
                "Une contrainte de base de données a empêché l'enregistrement. " .
                "Si le problème persiste, contactez un administrateur."
            );
        }

        // Re-throw a clean 422 — Turbo treats 422 as a form-rejection and
        // re-renders the response body in place. The body is whatever
        // Symfony's error renderer produces for the exception (with the
        // flash visible). Controllers that want better UX should catch the
        // exception themselves and re-render their form.
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
