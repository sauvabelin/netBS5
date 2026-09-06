<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Dto;

/**
 * Form-backing object for an OAuth client.
 *
 * Hydra is the source of truth — the local DB no longer stores client rows.
 * This DTO carries data between the Hydra admin API and the Symfony form,
 * converting between Hydra's JSON shape and our internal field names in two
 * tiny adapter methods.
 *
 * The `slug` field is the OAuth `client_id`. Empty when the form is filled
 * for a new client; populated when editing.
 */
final class OidcClientDto
{
    public string $slug = '';
    public string $name = '';

    /** @var list<string> */
    public array $redirectUris = [];

    /** @var list<string> */
    public array $postLogoutRedirectUris = [];

    public ?string $backchannelLogoutUri = null;

    /** Shown on the netBS login page when this client initiates an OIDC flow. */
    public ?string $logoUri = null;

    public string $scopes = 'openid profile email';

    /** @var list<string> */
    public array $allowedClaims = ['sub', 'preferred_username', 'email', 'name'];

    /**
     * Build a DTO from a Hydra `GET /admin/clients/{id}` response body.
     *
     * @param array<string, mixed> $hydra
     */
    public static function fromHydra(array $hydra): self
    {
        $dto = new self();
        $dto->slug                   = (string) ($hydra['client_id'] ?? '');
        $dto->name                   = (string) ($hydra['client_name'] ?? '');
        $dto->redirectUris           = self::stringList($hydra['redirect_uris'] ?? []);
        $dto->postLogoutRedirectUris = self::stringList($hydra['post_logout_redirect_uris'] ?? []);
        $dto->backchannelLogoutUri   = isset($hydra['backchannel_logout_uri']) && $hydra['backchannel_logout_uri'] !== ''
            ? (string) $hydra['backchannel_logout_uri']
            : null;
        $dto->logoUri                = isset($hydra['logo_uri']) && $hydra['logo_uri'] !== ''
            ? (string) $hydra['logo_uri']
            : null;
        $dto->scopes                 = (string) ($hydra['scope'] ?? 'openid profile email');
        $dto->allowedClaims          = self::stringList($hydra['metadata']['allowed_claims'] ?? $dto->allowedClaims);
        return $dto;
    }

    /**
     * Project the DTO to the JSON shape Hydra expects on POST/PUT.
     *
     * `$plaintextSecret` is included only when set — on create (Hydra
     * generates one if omitted) or on explicit secret regeneration.
     *
     * @return array<string, mixed>
     */
    public function toHydraPayload(?string $plaintextSecret = null): array
    {
        $payload = [
            'client_id'                  => $this->slug,
            'client_name'                => $this->name,
            'redirect_uris'              => array_values($this->redirectUris),
            'post_logout_redirect_uris'  => array_values($this->postLogoutRedirectUris),
            'scope'                      => $this->scopes,
            'grant_types'                => ['authorization_code', 'refresh_token'],
            'response_types'             => ['code'],
            'token_endpoint_auth_method' => 'client_secret_post',
            'metadata'                   => [
                'allowed_claims' => array_values($this->allowedClaims),
            ],
        ];

        if ($this->backchannelLogoutUri !== null && $this->backchannelLogoutUri !== '') {
            $payload['backchannel_logout_uri'] = $this->backchannelLogoutUri;
        }

        if ($this->logoUri !== null && $this->logoUri !== '') {
            $payload['logo_uri'] = $this->logoUri;
        }

        if ($plaintextSecret !== null && $plaintextSecret !== '') {
            $payload['client_secret'] = $plaintextSecret;
        }

        return $payload;
    }

    /**
     * @param mixed $raw
     * @return list<string>
     */
    private static function stringList($raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        return array_values(array_map(static fn ($v) => (string) $v, $raw));
    }
}
