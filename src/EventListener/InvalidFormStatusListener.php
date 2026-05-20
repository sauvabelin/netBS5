<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Form\Extension\InvalidFormStatusExtension;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Bumps an HTML 200 response to 422 when the request submitted an invalid form.
 *
 * Turbo only swaps the page when a form response is a redirect or a 4xx/5xx.
 * Symfony controllers conventionally re-render the form on validation failure
 * with status 200, which Turbo silently discards — the user sees nothing
 * change after Valider. Returning 422 instead is the HTTP-semantic correct
 * status ("the server understood the request but cannot process it") and
 * Turbo handles it natively: the response body replaces the page.
 *
 * We only touch successful HTML responses; redirects, JSON, downloads, etc.
 * pass through untouched.
 *
 * Paired with {@see InvalidFormStatusExtension} which sets the request mark.
 */
final class InvalidFormStatusListener
{
    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ($request->attributes->get(InvalidFormStatusExtension::REQUEST_ATTRIBUTE) !== true) {
            return;
        }

        $response = $event->getResponse();
        if ($response->getStatusCode() !== Response::HTTP_OK) {
            return;
        }

        $contentType = strtolower($response->headers->get('Content-Type', ''));
        if (!str_starts_with($contentType, 'text/html')
            && !str_starts_with($contentType, 'application/xhtml+xml')) {
            return;
        }

        $response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
    }
}
