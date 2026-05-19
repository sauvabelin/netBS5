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
        $response = $this->http->request('PUT', '/admin/oauth2/auth/requests/consent/accept', [
            'query' => ['consent_challenge' => $consentChallenge],
            'json' => $payload,
        ]);
        if ($response->getStatusCode() >= 400) {
            throw new \RuntimeException(sprintf(
                'Hydra accept-consent failed: %s | payload sent: %s',
                $response->getContent(false),
                json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ));
        }
        return $response->toArray();
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

    /**
     * Fetch a single OAuth client by id. Returns null when Hydra responds 404
     * so callers can treat "not found" as a regular case (e.g. when an admin
     * navigates to a stale URL).
     *
     * @return array<string, mixed>|null
     */
    public function getOAuthClient(string $clientId): ?array
    {
        $response = $this->http->request('GET', '/admin/clients/' . rawurlencode($clientId));
        if ($response->getStatusCode() === 404) {
            return null;
        }
        return $response->toArray();
    }

    /**
     * Return every OAuth client Hydra knows about. The admin panel calls this
     * once per list page render; we paginate up to a generous cap because
     * realistic deployments have under 50 clients.
     *
     * @return list<array<string, mixed>>
     */
    public function listOAuthClients(int $pageSize = 250): array
    {
        return $this->http->request('GET', '/admin/clients', [
            'query' => ['page_size' => $pageSize],
        ])->toArray();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createOAuthClient(array $payload): array
    {
        return $this->http->request('POST', '/admin/clients', [
            'json' => $payload,
        ])->toArray();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateOAuthClient(string $clientId, array $payload): array
    {
        return $this->http->request('PUT', '/admin/clients/' . rawurlencode($clientId), [
            'json' => $payload,
        ])->toArray();
    }

    public function deleteOAuthClient(string $clientId): void
    {
        $this->http->request('DELETE', '/admin/clients/' . rawurlencode($clientId))
            ->getStatusCode();
    }
}
