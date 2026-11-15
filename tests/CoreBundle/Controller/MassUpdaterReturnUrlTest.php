<?php

declare(strict_types=1);

namespace App\Tests\CoreBundle\Controller;

use NetBS\CoreBundle\Controller\MassUpdaterController;
use PHPUnit\Framework\TestCase;

/** After saving, the mass updater redirects to a client-supplied URL: it must stay on this site. */
final class MassUpdaterReturnUrlTest extends TestCase
{
    private const HOST = 'https://netbs.example';

    /** @dataProvider urls */
    public function test_return_url(string $url, bool $safe): void
    {
        $this->assertSame($safe, MassUpdaterController::isSafeReturnUrl($url, self::HOST));
    }

    public static function urls(): array
    {
        return [
            'relative path'            => ['/netBS/fichier/membre/12', true],
            'same host'                => ['https://netbs.example/netBS/x', true],
            'protocol-relative'        => ['//evil.example/x', false],
            'backslash trick'          => ['/\\evil.example/x', false],
            'host prefix of evil host' => ['https://netbs.example.evil.example/x', false],
            'other host'               => ['https://evil.example/', false],
        ];
    }
}
