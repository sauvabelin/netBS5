<?php

declare(strict_types=1);

namespace App\EventListener;

use NetBS\CoreBundle\Form\FormResponseAttributes;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Bumps a 200 HTML reply to 422 when the request submitted an invalid root form.
 * Turbo discards non-redirect 200s on form submissions but natively re-renders
 * 4xx — so 422 is what makes the failed-submit re-render work end-to-end.
 */
final class InvalidFormStatusListener
{
    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ($request->attributes->get(FormResponseAttributes::ROOT_INVALID) !== true) {
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
