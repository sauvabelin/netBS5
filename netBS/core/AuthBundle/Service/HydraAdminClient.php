<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Service;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Wrapper around Ory Hydra v2 admin API.
 *
 * All paths are on the admin port (HYDRA_ADMIN_URL, internal). Exact
 * request/response shapes follow the Ory Hydra v2 OpenAPI spec.
 */
final class HydraAdminClient
{
    private HttpClientInterface $http;

    public function __construct(string $hydraAdminUrl)
    {
        $this->http = HttpClient::createForBaseUri($hydraAdminUrl);
    }

    public function getLoginRequest(string $loginChallenge): array
    {
        return $this->http->request('GET', '/admin/oauth2/auth/requests/login', [
            'query' => ['login_challenge' => $loginChallenge],
        ])->toArray();
    }

    public function acceptLoginRequest(string $loginChallenge, array $payload): array
    {
        return $this->http->request('PUT', '/admin/oauth2/auth/requests/login/accept', [
            'query' => ['login_challenge' => $loginChallenge],
            'json' => $payload,
        ])->toArray();
    }

    public function rejectLoginRequest(string $loginChallenge, array $payload): array
    {
        return $this->http->request('PUT', '/admin/oauth2/auth/requests/login/reject', [
            'query' => ['login_challenge' => $loginChallenge],
            'json' => $payload,
        ])->toArray();
    }

    public function getConsentRequest(string $consentChallenge): array
    {
        return $this->http->request('GET', '/admin/oauth2/auth/requests/consent', [
            'query' => ['consent_challenge' => $consentChallenge],
        ])->toArray();
    }

    public function acceptConsentRequest(string $consentChallenge, array $payload): array
    {
        return $this->http->request('PUT', '/admin/oauth2/auth/requests/consent/accept', [
            'query' => ['consent_challenge' => $consentChallenge],
            'json' => $payload,
        ])->toArray();
    }

    public function rejectConsentRequest(string $consentChallenge, array $payload): array
    {
        return $this->http->request('PUT', '/admin/oauth2/auth/requests/consent/reject', [
            'query' => ['consent_challenge' => $consentChallenge],
            'json' => $payload,
        ])->toArray();
    }

    public function getLogoutRequest(string $logoutChallenge): array
    {
        return $this->http->request('GET', '/admin/oauth2/auth/requests/logout', [
            'query' => ['logout_challenge' => $logoutChallenge],
        ])->toArray();
    }

    public function acceptLogoutRequest(string $logoutChallenge): array
    {
        return $this->http->request('PUT', '/admin/oauth2/auth/requests/logout/accept', [
            'query' => ['logout_challenge' => $logoutChallenge],
        ])->toArray();
    }

    public function revokeAllSessionsForSubject(string $subject): void
    {
        $this->http->request('DELETE', '/admin/oauth2/auth/sessions/consent', [
            'query' => ['subject' => $subject, 'all' => 'true'],
        ])->getStatusCode();
    }
}
