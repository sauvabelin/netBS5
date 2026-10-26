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
        return new HydraAdminClient($mock, new \Psr\Log\NullLogger());
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

    public function testGetOAuthClientReturnsNullOn404(): void
    {
        $mock = new MockHttpClient([
            new MockResponse('', ['http_code' => 404]),
        ]);
        $client = $this->clientWith($mock);

        $this->assertNull($client->getOAuthClient('missing'));
    }

}
