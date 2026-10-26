<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Directory;

use NetBS\AuthBundle\Directory\LldapClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class LldapClientTest extends TestCase
{
    public function testExpiredTokenIsRenewedOnce(): void
    {
        $http = new MockHttpClient(baseUri: 'http://lldap.test', responseFactory: [
            $this->json(['token' => 'old']),
            new MockResponse('', ['http_code' => 401]),
            $this->json(['token' => 'new']),
            $this->json(['data' => ['schema' => ['groupSchema' => ['attributes' => [['name' => 'netbs-id']]]]]]),
        ]);

        self::assertSame(['netbs-id'], $this->client($http)->groupAttributes());
        self::assertSame(4, $http->getRequestsCount());
    }

    private function client(MockHttpClient $http): LldapClient
    {
        return new LldapClient($http, 'admin', 'secret');
    }

    private function json(array $data): MockResponse
    {
        return new MockResponse(json_encode($data, JSON_THROW_ON_ERROR), ['response_headers' => ['content-type' => 'application/json']]);
    }
}
