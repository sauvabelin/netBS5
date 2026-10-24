<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Wrapper around Ory Hydra v2 admin API.
 *
 * All paths are on the admin port (HYDRA_ADMIN_URL, internal). Exact
 * request/response shapes follow the Ory Hydra v2 OpenAPI spec.
 *
 * Every method enforces a uniform error contract: non-2xx responses and
 * transport failures are converted to {@see HydraClientException} (with a
 * structured warning logged). Callers should treat any thrown exception as
 * "the request did not succeed" and decide whether to fail loudly or surface
 * a friendly error.
 */
final class HydraAdminClient
{
    private const RESPONSE_EXCERPT_LIMIT = 500;

    /**
     * @param HttpClientInterface $http scoped to HYDRA_ADMIN_URL (`hydra.admin.client`)
     */
    public function __construct(
        private readonly HttpClientInterface $http,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function getLoginRequest(string $loginChallenge): array
    {
        return $this->jsonRequest('GET', '/admin/oauth2/auth/requests/login', [
            'query' => ['login_challenge' => $loginChallenge],
        ]);
    }

    public function acceptLoginRequest(string $loginChallenge, array $payload): array
    {
        return $this->jsonRequest('PUT', '/admin/oauth2/auth/requests/login/accept', [
            'query' => ['login_challenge' => $loginChallenge],
            'json' => $payload,
        ]);
    }

    public function rejectLoginRequest(string $loginChallenge, array $payload): array
    {
        return $this->jsonRequest('PUT', '/admin/oauth2/auth/requests/login/reject', [
            'query' => ['login_challenge' => $loginChallenge],
            'json' => $payload,
        ]);
    }

    public function getConsentRequest(string $consentChallenge): array
    {
        return $this->jsonRequest('GET', '/admin/oauth2/auth/requests/consent', [
            'query' => ['consent_challenge' => $consentChallenge],
        ]);
    }

    public function acceptConsentRequest(string $consentChallenge, array $payload): array
    {
        return $this->jsonRequest('PUT', '/admin/oauth2/auth/requests/consent/accept', [
            'query' => ['consent_challenge' => $consentChallenge],
            'json' => $payload,
        ]);
    }

    public function rejectConsentRequest(string $consentChallenge, array $payload): array
    {
        return $this->jsonRequest('PUT', '/admin/oauth2/auth/requests/consent/reject', [
            'query' => ['consent_challenge' => $consentChallenge],
            'json' => $payload,
        ]);
    }

    public function getLogoutRequest(string $logoutChallenge): array
    {
        return $this->jsonRequest('GET', '/admin/oauth2/auth/requests/logout', [
            'query' => ['logout_challenge' => $logoutChallenge],
        ]);
    }

    public function acceptLogoutRequest(string $logoutChallenge): array
    {
        return $this->jsonRequest('PUT', '/admin/oauth2/auth/requests/logout/accept', [
            'query' => ['logout_challenge' => $logoutChallenge],
        ]);
    }

    /**
     * Revoke all OAuth consent sessions for a subject. Hydra returns 204 on
     * success; anything else is treated as a hard failure so callers can't
     * mistake "session lingered" for "session cleared".
     */
    public function revokeAllSessionsForSubject(string $subject): void
    {
        $this->voidRequest('DELETE', '/admin/oauth2/auth/sessions/consent', [
            'query' => ['subject' => $subject, 'all' => 'true'],
        ]);
    }

    /**
     * End every Hydra login session (the SSO "remember me") of a subject, so
     * the next RP login goes back through netBS instead of being skipped.
     */
    public function revokeLoginSessionsForSubject(string $subject): void
    {
        $this->voidRequest('DELETE', '/admin/oauth2/auth/sessions/login', [
            'query' => ['subject' => $subject],
        ]);
    }

    /**
     * End one Hydra login session, identified by the `session_id` Hydra puts
     * on login requests (one browser's SSO session).
     */
    public function revokeLoginSession(string $sessionId): void
    {
        $this->voidRequest('DELETE', '/admin/oauth2/auth/sessions/login', [
            'query' => ['sid' => $sessionId],
        ]);
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
        return $this->jsonRequest('GET', '/admin/clients/' . rawurlencode($clientId), nullOn404: true);
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
        return $this->jsonRequest('GET', '/admin/clients', [
            'query' => ['page_size' => $pageSize],
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function createOAuthClient(array $payload): array
    {
        return $this->jsonRequest('POST', '/admin/clients', [
            'json' => $payload,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function updateOAuthClient(string $clientId, array $payload): array
    {
        return $this->jsonRequest('PUT', '/admin/clients/' . rawurlencode($clientId), [
            'json' => $payload,
        ]);
    }

    public function deleteOAuthClient(string $clientId): void
    {
        $this->voidRequest('DELETE', '/admin/clients/' . rawurlencode($clientId));
    }

    /**
     * @param array<string, mixed> $options
     * @return array<string, mixed>|null null only on a 404 with $nullOn404
     */
    private function jsonRequest(string $method, string $url, array $options = [], bool $nullOn404 = false): ?array
    {
        try {
            $response = $this->http->request($method, $url, $options);
            $status = $response->getStatusCode();
            if ($nullOn404 && $status === 404) {
                return null;
            }
            if ($status >= 400) {
                $this->fail($method, $url, $response);
            }
            return $response->toArray();
        } catch (TransportExceptionInterface $e) {
            $this->failTransport($method, $url, $e);
        } catch (HttpClientExceptionInterface $e) {
            // toArray() can throw on non-2xx even though we already checked;
            // also covers JSON-decode failures. Re-wrap uniformly.
            $this->logger->warning('hydra.client: response decoding failed', [
                'method' => $method,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            throw new HydraClientException($method, $url, 0, $e->getMessage(), $e);
        }
    }

    /**
     * @param array<string, mixed> $options
     */
    private function voidRequest(string $method, string $url, array $options = []): void
    {
        try {
            $response = $this->http->request($method, $url, $options);
            $status = $response->getStatusCode();
            if ($status >= 400) {
                $this->fail($method, $url, $response);
            }
        } catch (TransportExceptionInterface $e) {
            $this->failTransport($method, $url, $e);
        }
    }

    /**
     * @return never
     */
    private function fail(string $method, string $url, ResponseInterface $response): void
    {
        $status = $response->getStatusCode();
        $excerpt = substr((string) $response->getContent(false), 0, self::RESPONSE_EXCERPT_LIMIT);

        $this->logger->warning('hydra.client: non-2xx response', [
            'method' => $method,
            'url' => $url,
            'status' => $status,
            'response_excerpt' => $excerpt,
        ]);

        throw new HydraClientException($method, $url, $status, $excerpt);
    }

    /**
     * @return never
     */
    private function failTransport(string $method, string $url, TransportExceptionInterface $e): void
    {
        $this->logger->warning('hydra.client: transport failure', [
            'method' => $method,
            'url' => $url,
            'error' => $e->getMessage(),
        ]);

        throw new HydraClientException($method, $url, 0, 'transport: ' . $e->getMessage(), $e);
    }
}
