<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * /oidc-error takes its error fields from the query string, so anyone can link a
 * victim to it. Outside dev, nothing the caller supplies may reach the page.
 */
final class ErrorControllerTest extends WebTestCase
{
    private const MARKER = 'attackerMarker7f3a';

    /** @dataProvider codes */
    public function test_caller_text_never_reaches_the_page(string $error, string $expectedCode): void
    {
        $client = static::createClient();
        $client->request('GET', '/oidc-error', [
            'error'             => $error,
            'error_description' => self::MARKER . ' call +41 00 000 00 00',
            'error_hint'        => self::MARKER,
            'error_debug'       => self::MARKER,
        ]);

        $this->assertResponseStatusCodeSame(403);
        $this->assertStringNotContainsString(self::MARKER, $client->getResponse()->getContent());
        $this->assertSelectorTextSame('code', $expectedCode);
    }

    public static function codes(): iterable
    {
        yield 'known code' => ['access_denied', 'access_denied'];
        yield 'casing is normalised' => ['ACCESS_DENIED', 'access_denied'];
        yield 'markup is stripped, the rest must still be whitelisted' => ['access_denied<script>', 'unknown'];
        yield 'unknown code' => [self::MARKER, 'unknown'];
    }
}
