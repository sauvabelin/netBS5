<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Controller\Identity;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Renders the post-Hydra OIDC error page.
 *
 * Security note: Hydra redirects users here with `error`, `error_description`,
 * `error_hint`, and `error_debug` query parameters. Those values are attacker-
 * controllable (anybody can craft a link to /oidc-error?error_description=...)
 * and rendering them verbatim turns this netBS-branded page into a phishing
 * template. We instead map the well-known OAuth2/OIDC error codes (RFC 6749 §4.1.2.1,
 * §4.2.2.1, §5.2, plus OIDC core §3.1.2.6) to curated user-facing messages and
 * silently drop the raw description/hint/debug on non-dev environments. The full
 * payload is logged so operators can still debug real failures.
 */
final class ErrorController extends AbstractController
{
    /**
     * Whitelist of standard OAuth2/OIDC error codes → curated French user messages.
     *
     * Keep messages generic enough to avoid leaking server internals but specific
     * enough to be actionable. Any code not in this map falls back to UNKNOWN_MESSAGE.
     */
    private const ERROR_MESSAGES = [
        // RFC 6749 §4.1.2.1 / §4.2.2.1 / §5.2
        'invalid_request'           => "La requête d'authentification est invalide.",
        'invalid_client'            => "L'application cliente n'a pas pu être authentifiée.",
        'invalid_grant'             => "L'autorisation a expiré ou n'est plus valide. Veuillez vous reconnecter.",
        'unauthorized_client'       => "L'application cliente n'est pas autorisée à effectuer cette action.",
        'unsupported_response_type' => "Le type de réponse demandé n'est pas pris en charge.",
        'unsupported_grant_type'    => "Le type d'autorisation demandé n'est pas pris en charge.",
        'invalid_scope'             => "Les permissions demandées sont invalides ou non autorisées.",
        'access_denied'             => "Vous n'avez pas accès à cette application.",
        'server_error'              => "Une erreur interne est survenue lors de l'authentification.",
        'temporarily_unavailable'   => "Le service d'authentification est temporairement indisponible.",
        // OIDC core §3.1.2.6
        'login_required'            => "Une connexion est requise pour accéder à cette application.",
        'consent_required'          => "Votre consentement est requis pour accéder à cette application.",
        'interaction_required'      => "Une interaction utilisateur est requise pour continuer.",
        'account_selection_required' => "Veuillez sélectionner un compte pour continuer.",
    ];

    private const UNKNOWN_MESSAGE = "Une erreur d'authentification est survenue.";

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    #[Route('/oidc-error', name: 'oidc_error', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        // Raw upstream values — NEVER trust these for end-user rendering.
        $rawError       = (string) $request->query->get('error', '');
        $rawDescription = (string) $request->query->get('error_description', '');
        $rawHint        = (string) $request->query->get('error_hint', '');
        $rawDebug       = (string) $request->query->get('error_debug', '');

        $normalisedCode = $this->normaliseCode($rawError);
        $userMessage    = self::ERROR_MESSAGES[$normalisedCode] ?? self::UNKNOWN_MESSAGE;
        $isKnownCode    = \array_key_exists($normalisedCode, self::ERROR_MESSAGES);

        // Log the FULL incoming payload so operators can debug real failures
        // without exposing attacker-supplied text to victims.
        ($this->logger ?? new NullLogger())->warning('OIDC error page reached', [
            'error'             => $rawError,
            'error_description' => $rawDescription,
            'error_hint'        => $rawHint,
            'error_debug'       => $rawDebug,
            'referrer'          => $request->headers->get('referer'),
            'normalised_code'   => $normalisedCode,
            'is_known_code'     => $isKnownCode,
        ]);

        $isDev = $this->kernel->getEnvironment() === 'dev';

        return $this->render('@NetBSAuth/identity/error.html.twig', [
            // Curated, safe values intended for end-user display.
            'errorCode'    => $isKnownCode ? $normalisedCode : 'unknown',
            'userMessage'  => $userMessage,
            // Raw upstream values are passed ONLY in dev, and the template clearly
            // labels them as such. In prod they are not exposed to the page.
            'devDetails'   => $isDev ? [
                'error'             => $rawError,
                'error_description' => $rawDescription,
                'error_hint'        => $rawHint,
                'error_debug'       => $rawDebug,
            ] : null,
        ], new Response('', Response::HTTP_FORBIDDEN));
    }

    /**
     * Lowercase + strip non-[a-z0-9_] so a crafted value like `Invalid Request`
     * or `access_denied<script>` doesn't bypass the whitelist via casing/extras.
     */
    private function normaliseCode(string $code): string
    {
        $code = strtolower($code);
        return preg_replace('/[^a-z0-9_]/', '', $code) ?? '';
    }
}
