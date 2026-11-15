<?php

declare(strict_types=1);

namespace App\Tests\Galerie;

use App\Model\Directory;
use PHPUnit\Framework\TestCase;

/** The galerie API resolves client-supplied hashed paths; they must never leave the galerie root. */
final class DirectoryHashPathTest extends TestCase
{
    private string $base;
    private string $root;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/galerie-test-' . uniqid();
        $this->root = $this->base . '/galerie';
        mkdir($this->root . '/Camp 2026', 0777, true);
        mkdir($this->base . '/secret');
    }

    protected function tearDown(): void
    {
        rmdir($this->root . '/Camp 2026');
        rmdir($this->root);
        rmdir($this->base . '/secret');
        rmdir($this->base);
    }

    private function assertInsideRoot(string $resolved): void
    {
        $this->assertStringStartsWith(realpath($this->root), (string) realpath($resolved));
    }

    public function test_resolves_a_hashed_child_directory(): void
    {
        $this->assertSame(realpath($this->root . '/Camp 2026'), realpath(Directory::resolveHashPath($this->root, 'camp-2026')));
    }

    public function test_dot_dot_does_not_climb_above_the_root(): void
    {
        $this->assertInsideRoot(Directory::resolveHashPath($this->root, '..'));
        $this->assertInsideRoot(Directory::resolveHashPath($this->root, '../secret'));
        $this->assertInsideRoot(Directory::resolveHashPath($this->root, 'camp-2026/../..'));
    }
}
