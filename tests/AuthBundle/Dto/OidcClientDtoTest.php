<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Dto;

use NetBS\AuthBundle\Dto\OidcClientDto;
use PHPUnit\Framework\TestCase;

/**
 * Hydra's PUT replaces the whole client, so editing through the admin form
 * must not wipe the fields the form doesn't manage.
 */
final class OidcClientDtoTest extends TestCase
{
    private function existingClient(): array
    {
        return [
            'client_id' => 'nextcloud',
            'client_name' => 'Stammbox',
            'redirect_uris' => ['https://cloud.example/cb'],
            'post_logout_redirect_uris' => [],
            'scope' => 'openid profile',
            'grant_types' => ['authorization_code', 'refresh_token'],
            'response_types' => ['code'],
            'token_endpoint_auth_method' => 'client_secret_basic',
            'skip_consent' => true,
            'authorization_code_grant_access_token_lifespan' => '1h',
            'backchannel_logout_uri' => 'https://cloud.example/bcl',
            'metadata' => ['allowed_claims' => ['sub'], 'note' => 'keep me', 'required_flag' => 'nextcloud_account'],
            'created_at' => '2026-01-01T00:00:00Z',
            'updated_at' => '2026-01-01T00:00:00Z',
        ];
    }

    public function testEditKeepsUnmanagedFields(): void
    {
        $dto = OidcClientDto::fromHydra($this->existingClient());
        $dto->name = 'Stammbox renamed';
        $dto->allowedClaims = ['sub', 'email'];

        $payload = $dto->toHydraPayload();

        $this->assertSame('Stammbox renamed', $payload['client_name']);
        $this->assertSame('client_secret_basic', $payload['token_endpoint_auth_method']);
        $this->assertTrue($payload['skip_consent']);
        $this->assertSame('1h', $payload['authorization_code_grant_access_token_lifespan']);
        $this->assertSame(['sub', 'email'], $payload['metadata']['allowed_claims']);
        $this->assertSame('keep me', $payload['metadata']['note']);
        $this->assertSame('nextcloud_account', $payload['metadata']['required_flag']);
        $this->assertArrayNotHasKey('created_at', $payload);
        $this->assertArrayNotHasKey('updated_at', $payload);
    }

    public function testClearedOptionalFieldsAreRemoved(): void
    {
        $dto = OidcClientDto::fromHydra($this->existingClient());
        $dto->backchannelLogoutUri = null;
        $dto->requiredFlag = null;

        $payload = $dto->toHydraPayload();

        $this->assertArrayNotHasKey('backchannel_logout_uri', $payload);
        $this->assertArrayNotHasKey('required_flag', $payload['metadata']);
    }

    public function testEmptyJwksIsSentBackAsAnObject(): void
    {
        // Hydra returns `"jwks": {}`; json_decode turns it into [], which must
        // not go back out as `[]` (Hydra rejects the whole PUT).
        $dto = OidcClientDto::fromHydra(['jwks' => []] + $this->existingClient());

        $json = json_encode($dto->toHydraPayload(), JSON_THROW_ON_ERROR);

        $this->assertStringContainsString('"jwks":{}', $json);
    }
}
