<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Service;

use NetBS\AuthBundle\Service\HydraAdminClient;
use NetBS\AuthBundle\Service\HydraClientException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class HydraAdminClientTest extends TestCase
{
    private function clientWith(MockHttpClient $mock): HydraAdminClient
    {
        $client = (new \ReflectionClass(HydraAdminClient::class))
            ->newInstanceWithoutConstructor();

        $httpProp = new \ReflectionProperty(HydraAdminClient::class, 'http');
        $httpProp->setAccessible(true);
        $httpProp->setValue($client, $mock);

        $loggerProp = new \ReflectionProperty(HydraAdminClient::class, 'logger');
        $loggerProp->setAccessible(true);
        $loggerProp->setValue($client, new \Psr\Log\NullLogger());

        return $client;
    }

    public function testNon2xxThrowsHydraClientException(): void
    {
        $mock = new MockHttpClient([
            new MockResponse('{"error":"not_found"}', ['http_code' => 404]),
        ]);
        $client = $this->clientWith($mock);

        try {
            $client->acceptConsentRequest('ch', ['grant_scope' => []]);
            $this->fail('Expected HydraClientException');
        } catch (HydraClientException $e) {
            $this->assertSame(404, $e->statusCode);
            $this->assertSame('PUT', $e->method);
            $this->assertStringContainsString('not_found', $e->responseExcerpt);
        }
    }

    public function testRevokeAllSessionsRaisesOnNon2xx(): void
    {
        $mock = new MockHttpClient([
            new MockResponse('boom', ['http_code' => 500]),
        ]);
        $client = $this->clientWith($mock);

        $this->expectException(HydraClientException::class);
        $client->revokeAllSessionsForSubject('alice');
    }

    public function testDeleteOAuthClientRaisesOnNon2xx(): void
    {
        $mock = new MockHttpClient([
            new MockResponse('forbidden', ['http_code' => 403]),
        ]);
        $client = $this->clientWith($mock);

        $this->expectException(HydraClientException::class);
        $client->deleteOAuthClient('my-client');
    }

    public function testGetOAuthClientReturnsNullOn404(): void
    {
        $mock = new MockHttpClient([
            new MockResponse('', ['http_code' => 404]),
        ]);
        $client = $this->clientWith($mock);

        $this->assertNull($client->getOAuthClient('missing'));
    }

    public function testGetOAuthClientRaisesOnOtherErrors(): void
    {
        $mock = new MockHttpClient([
            new MockResponse('server down', ['http_code' => 503]),
        ]);
        $client = $this->clientWith($mock);

        $this->expectException(HydraClientException::class);
        $client->getOAuthClient('foo');
    }

    public function testSuccessfulRequestReturnsDecodedJson(): void
    {
        $mock = new MockHttpClient([
            new MockResponse('{"redirect_to":"https://x/cb"}', ['http_code' => 200]),
        ]);
        $client = $this->clientWith($mock);

        $result = $client->acceptLoginRequest('ch', ['subject' => 'alice']);
        $this->assertSame('https://x/cb', $result['redirect_to']);
    }
}
