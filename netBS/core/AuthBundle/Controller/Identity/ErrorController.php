<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Controller\Identity;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ErrorController extends AbstractController
{
    #[Route('/oidc-error', name: 'oidc_error', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        return $this->render('@NetBSAuth/identity/error.html.twig', [
            'error' => $request->query->get('error', 'unknown'),
            'errorDescription' => $request->query->get('error_description', ''),
            'errorHint' => $request->query->get('error_hint', ''),
        ], new Response('', Response::HTTP_FORBIDDEN));
    }
}
