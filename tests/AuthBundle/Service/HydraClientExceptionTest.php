<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Service;

use NetBS\AuthBundle\Service\HydraClientException;
use PHPUnit\Framework\TestCase;

final class HydraClientExceptionTest extends TestCase
{
    public function testCarriesStructuredContextOnFailure(): void
    {
        $e = new HydraClientException(
            method: 'PUT',
            url: '/admin/oauth2/auth/requests/consent/accept',
            statusCode: 409,
            responseExcerpt: '{"error":"conflict"}',
        );

        $this->assertSame('PUT', $e->method);
        $this->assertSame(409, $e->statusCode);
        $this->assertStringContainsString('PUT', $e->getMessage());
        $this->assertStringContainsString('409', $e->getMessage());
        $this->assertStringContainsString('conflict', $e->getMessage());
    }

    public function testIsRuntimeException(): void
    {
        $e = new HydraClientException('GET', '/x', 0, 'transport: dns failure');
        $this->assertInstanceOf(\RuntimeException::class, $e);
    }
}
