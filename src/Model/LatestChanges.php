<?php

namespace App\Model;

/**
 * Finds the most recently changed albums by walking the galerie originals on disk.
 *
 * A directory's mtime moves when entries are added, removed or renamed directly inside
 * it (Nextcloud uploads land as .part files renamed into place, so new photos count).
 * Only albums that directly hold photos are reported: creating a sub-album also touches
 * its parent, which would otherwise show up as a change of its own.
 */
class LatestChanges
{
    public function __construct(private readonly GalerieConfig $config)
    {
    }

    /**
     * @return array<array{hash: string, path: string, name: string, date: string}> newest first
     */
    public function find(int $limit = 10): array
    {
        $root = $this->config->getFullMappedDirectory();
        if (!is_dir($root)) {
            return [];
        }

        $mtimes = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                // Only descend into visible directories; files are never needed here.
                fn (\SplFileInfo $entry) => $entry->isDir() && !str_starts_with($entry->getFilename(), '.')
            ),
            \RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $path => $entry) {
            $mtimes[$path] = $entry->getMTime();
        }
        arsort($mtimes);

        $changes = [];
        foreach ($mtimes as $path => $mtime) {
            $directory = new Directory($path, $this->config);
            if (count($directory->getMedias()) === 0 || $this->lastChangeWasDescription($path, $mtime)) {
                continue;
            }

            $relativePath = ltrim($directory->getRelativePath(), '/');
            $changes[] = [
                'hash' => ltrim($directory->getHashPath(), '/'),
                'path' => $relativePath,
                'name' => $directory->getName(),
                'date' => date('d.m.Y', $mtime),
            ];

            if (count($changes) === $limit) {
                break;
            }
        }

        return $changes;
    }

    /** Editing an album's description is not a change worth announcing. */
    private function lastChangeWasDescription(string $path, int $dirMtime): bool
    {
        foreach ($this->config->getDescriptionFilename() as $filename) {
            $file = $path . '/' . $filename;
            if (is_file($file) && filemtime($file) >= $dirMtime) {
                return true;
            }
        }

        return false;
    }
}
