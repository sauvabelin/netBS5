<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Controller\Admin;

use NetBS\AuthBundle\Dto\OidcClientDto;
use NetBS\AuthBundle\Form\OidcClientType;
use NetBS\AuthBundle\Service\HydraAdminClient;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;

/**
 * Hydra-backed OAuth client admin. There is no local persistence — every
 * action is a thin shell that issues one or two Hydra admin API calls and
 * presents the result. The slug shown in the URL is the OAuth `client_id`.
 *
 * Plaintext secrets are surfaced exactly once: on creation via Hydra's POST
 * response, and on explicit regeneration when we send a generated secret as
 * part of the PUT body.
 */
#[Route('/clients')]
final class OidcClientController extends AbstractController
{
    public function __construct(
        private readonly HydraAdminClient $hydra,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('', name: 'auth.admin.oidc_clients.index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        try {
            $clients = $this->hydra->listOAuthClients();
        } catch (HttpExceptionInterface $e) {
            $this->logger->error('Hydra listOAuthClients failed', ['exception' => $e->getMessage()]);
            $this->addFlash('error', 'Could not list OAuth clients: ' . $e->getMessage());
            $clients = [];
        }

        return $this->render('@NetBSAuth/admin/oidc/list.html.twig', [
            'clients' => $clients,
        ]);
    }

    #[Route('/new', name: 'auth.admin.oidc_clients.create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('oidc_client_create', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $slug = $this->generateSlug();
        $dto = new OidcClientDto();
        $dto->slug = $slug;
        $dto->name = 'New client';

        $plaintext = $this->generateSecret();

        try {
            $created = $this->hydra->createOAuthClient($dto->toHydraPayload($plaintext));
        } catch (HttpExceptionInterface $e) {
            $this->logger->error('Hydra createOAuthClient failed', ['exception' => $e->getMessage()]);
            $this->addFlash('error', 'Hydra create failed: ' . $e->getMessage());
            return $this->redirectToRoute('auth.admin.oidc_clients.index');
        }

        // Hydra echoes the plaintext secret in the create response when we
        // supplied one. Prefer that value over the one we generated locally
        // so we always show the user exactly what Hydra now has.
        $secret = (string) ($created['client_secret'] ?? $plaintext);

        $this->addFlash('oidc_one_shot_secret', $secret);
        $this->addFlash('success', 'OAuth client created. The secret is shown below (only once).');

        return $this->redirectToRoute('auth.admin.oidc_clients.edit', ['slug' => $slug]);
    }

    #[Route('/{slug}/edit', name: 'auth.admin.oidc_clients.edit', methods: ['GET', 'POST'])]
    public function edit(string $slug, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $hydraClient = $this->hydra->getOAuthClient($slug);
        if ($hydraClient === null) {
            throw $this->createNotFoundException();
        }
        $dto = OidcClientDto::fromHydra($hydraClient);

        $form = $this->createForm(OidcClientType::class, $dto);
        $form->handleRequest($request);

        $status = Response::HTTP_OK;

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    $this->hydra->updateOAuthClient($slug, $dto->toHydraPayload());
                    $this->addFlash('success', 'Client updated.');
                    return $this->redirectToRoute('auth.admin.oidc_clients.index');
                } catch (HttpExceptionInterface $e) {
                    $this->logger->error('Hydra updateOAuthClient failed', ['exception' => $e->getMessage()]);
                    $this->addFlash('error', 'Hydra update failed: ' . $e->getMessage());
                }
            } else {
                $this->addFlash('error', 'The form contains errors. Please fix them and try again.');
                // Turbo expects 422 on POSTs that re-render an invalid form.
                $status = Response::HTTP_UNPROCESSABLE_ENTITY;
            }
        }

        return $this->render('@NetBSAuth/admin/oidc/edit.html.twig', [
            'client' => $dto,
            'form'   => $form->createView(),
        ], new Response('', $status));
    }

    #[Route('/{slug}/regenerate-secret', name: 'auth.admin.oidc_clients.regenerate_secret', methods: ['POST'])]
    public function regenerateSecret(string $slug, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('oidc_client_regenerate_' . $slug, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        $hydraClient = $this->hydra->getOAuthClient($slug);
        if ($hydraClient === null) {
            throw $this->createNotFoundException();
        }

        $dto = OidcClientDto::fromHydra($hydraClient);
        $plaintext = $this->generateSecret();

        try {
            $this->hydra->updateOAuthClient($slug, $dto->toHydraPayload($plaintext));
        } catch (HttpExceptionInterface $e) {
            $this->addFlash('error', 'Hydra error: ' . $e->getMessage());
            return $this->redirectToRoute('auth.admin.oidc_clients.edit', ['slug' => $slug]);
        }

        $this->addFlash('oidc_one_shot_secret', $plaintext);
        $this->addFlash('success', 'New secret generated (shown only once).');

        return $this->redirectToRoute('auth.admin.oidc_clients.edit', ['slug' => $slug]);
    }

    #[Route('/{slug}/delete', name: 'auth.admin.oidc_clients.delete', methods: ['POST'])]
    public function delete(string $slug, Request $request): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('oidc_client_delete_' . $slug, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }

        try {
            $this->hydra->deleteOAuthClient($slug);
        } catch (HttpExceptionInterface $e) {
            $this->addFlash('error', 'Hydra delete failed: ' . $e->getMessage());
            return $this->redirectToRoute('auth.admin.oidc_clients.edit', ['slug' => $slug]);
        }

        $this->addFlash('success', 'Client deleted.');
        return $this->redirectToRoute('auth.admin.oidc_clients.index');
    }

    private function generateSlug(): string
    {
        return 'client-' . bin2hex(random_bytes(4));
    }

    private function generateSecret(): string
    {
        // 32 bytes → 43-char URL-safe base64.
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }
}
