<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Service;

/**
 * Resolves the OIDC client context, if any, for the current login attempt.
 *
 * When the netBS login page is rendered as part of an OIDC flow, its URL
 * carries the Hydra `login_challenge` (see OidcLoginChallenge). We ask Hydra
 * for the client behind it so the template can show the requesting
 * application's branding.
 */
final class LoginContextProvider
{
    public function __construct(
        private readonly HydraAdminClient $hydra,
    ) {
    }

    /**
     * @return array{client_id: string, client_name: ?string, logo_uri: ?string}|null
     */
    public function currentClient(?string $loginChallenge): ?array
    {
        if ($loginChallenge === null) {
            return null;
        }

        try {
            $loginRequest = $this->hydra->getLoginRequest($loginChallenge);
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
