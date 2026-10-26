<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * Resolves the OIDC client context, if any, for the current login attempt.
 *
 * When the netBS login page is rendered as part of an OIDC flow, the OIDC
 * firewall has stored the intercepted URL under `_security.oidc.target_path`.
 * We pull the `login_challenge` out of that URL and ask Hydra for the client
 * so the template can show the requesting application's branding.
 */
final class LoginContextProvider
{
    use TargetPathTrait;

    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly HydraAdminClient $hydra,
    ) {
    }

    /**
     * @return array{client_id: string, client_name: ?string, logo_uri: ?string}|null
     */
    public function currentClient(): ?array
    {
        $targetPath = $this->getTargetPath($this->requestStack->getSession(), 'oidc');
        if ($targetPath === null || $targetPath === '') {
            return null;
        }

        $query = parse_url($targetPath, PHP_URL_QUERY);
        if (!is_string($query)) {
            return null;
        }
        parse_str($query, $params);
        $challenge = $params['login_challenge'] ?? null;
        if (!is_string($challenge) || $challenge === '') {
            return null;
        }

        try {
            $loginRequest = $this->hydra->getLoginRequest($challenge);
        } catch (HydraClientException) {
            // Challenge expired or unknown (or any other Hydra failure; the
            // client logged it): render the plain login form.
            return null;
        }

        $client = $loginRequest['client'] ?? [];

        return [
            'client_id'   => (string) ($client['client_id']   ?? ''),
            'client_name' => isset($client['client_name']) ? (string) $client['client_name'] : null,
            'logo_uri'    => isset($client['logo_uri'])    ? (string) $client['logo_uri']    : null,
        ];
    }
}
