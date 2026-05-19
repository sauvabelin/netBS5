<?php

declare(strict_types=1);

namespace App\Identity\Service;

use Symfony\Component\Yaml\Yaml;

final class ClientConfigLoader
{
    /** @return array<string, array{allowed_claims: string[]}> */
    public static function load(string $path): array
    {
        $data = Yaml::parseFile($path);
        return $data['clients'] ?? [];
    }
}
