<?php

declare(strict_types=1);

namespace App\Tests\Galerie;

use App\Model\GalerieConfig;
use App\Model\LatestChanges;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/** "Latest changes" on the galerie home page is computed from directory mtimes on disk. */
final class LatestChangesTest extends TestCase
{
    private string $base;
    private string $root;

    protected function setUp(): void
    {
        $this->base = sys_get_temp_dir() . '/galerie-latest-' . uniqid();
        $this->root = $this->base . '/originals';
        mkdir($this->root, 0777, true);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->base);
    }

    private function finder(): LatestChanges
    {
        return new LatestChanges(new GalerieConfig($this->base, '', '/originals', '/cache', ['jpg', 'png'], ['description.md']));
    }

    /** Creates an album holding the given files, then pins its mtime (files first: writing them bumps it). */
    private function album(string $relativePath, int $mtime, array $files = ['photo.jpg'], array $fileMtimes = []): void
    {
        $dir = $this->root . '/' . $relativePath;
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        foreach ($files as $file) {
            touch($dir . '/' . $file, $fileMtimes[$file] ?? $mtime - 10);
        }
        touch($dir, $mtime);
    }

    public function test_lists_albums_with_photos_newest_first(): void
    {
        $this->album('Camps/Camp été 2026', 2_000_000_000);
        $this->album('Sorties/Ski', 1_900_000_000, ['IMG_1.JPG']);
        $this->album('Sorties/Vide', 2_100_000_000, ['notes.txt']);
        $this->album('.hidden', 2_200_000_000);
        // Parents were touched when their sub-albums were created; they hold no photos themselves.
        touch($this->root . '/Camps', 2_000_000_001);
        touch($this->root . '/Sorties', 2_000_000_002);

        $changes = $this->finder()->find(10);

        $this->assertSame([
            ['hash' => 'camps/camp-ete-2026', 'path' => 'Camps/Camp été 2026', 'name' => 'Camp été 2026', 'date' => date('d.m.Y', 2_000_000_000)],
            ['hash' => 'sorties/ski', 'path' => 'Sorties/Ski', 'name' => 'Ski', 'date' => date('d.m.Y', 1_900_000_000)],
        ], $changes);
        $this->assertCount(1, $this->finder()->find(1));
    }

    public function test_skips_an_album_whose_last_change_was_its_description(): void
    {
        $this->album('Description seule', 2_000_000_000, ['photo.jpg', 'description.md'], ['description.md' => 2_000_000_000]);
        $this->album('Nouvelles photos', 1_900_000_000, ['photo.jpg', 'description.md'], ['description.md' => 1_000_000_000]);

        $this->assertSame(['Nouvelles photos'], array_column($this->finder()->find(10), 'name'));
    }

    public function test_missing_root_yields_no_changes(): void
    {
        (new Filesystem())->remove($this->root);

        $this->assertSame([], $this->finder()->find(10));
    }
}
