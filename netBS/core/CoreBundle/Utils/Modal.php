<?php

namespace NetBS\CoreBundle\Utils;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class Modal
{
    public static function refresh($content = null) {
        return new Response($content, 201);
    }

    public static function redirect(RedirectResponse $response) {

        $response->setStatusCode(200);
        $response->setContent("redirected");

        return $response;
    }

    public static function ack($message, $type = 'info') {
        return new JsonResponse(['type' => $type, 'message' => $message], 202);
    }

    public static function renderModal(FormInterface $form) {

        $code = $form->isSubmitted() && !$form->isValid() ? Response::HTTP_FORBIDDEN : Response::HTTP_OK;
        return new Response(null, $code);
    }
}
