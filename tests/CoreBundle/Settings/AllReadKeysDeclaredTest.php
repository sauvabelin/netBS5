<?php

declare(strict_types=1);

namespace App\Tests\CoreBundle\Settings;

use NetBS\CoreBundle\Settings\SettingsRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Finder\Finder;

/**
 * Guard: every setting the code reads by literal key (PHP getValue() / getValueOrNull(), Twig param(), the
 * Nextcloud SQL views) must be declared, so it is validated and shown on the Paramètres page.
 */
final class AllReadKeysDeclaredTest extends KernelTestCase
{
    private const CODE_PATTERNS = [
        '/getValue(?:OrNull)?\(\s*[\'"]([\w.]+)[\'"]\s*,\s*[\'"]([\w.]+)[\'"]/',
        '/param\(\s*[\'"]([\w.]+)[\'"]\s*,\s*[\'"]([\w.]+)[\'"]/',
    ];

    private const SQL_PATTERN = '/namespace\s*=\s*\'(\w+)\'\s+AND\s+p?\.?paramKey\s*(?:=|IN)\s*\(?([^)]+)/i';

    public function test_every_key_read_by_code_is_declared(): void
    {
        self::bootKernel();
        $registry   = self::getContainer()->get(SettingsRegistry::class);
        $undeclared = [];

        foreach ($this->codeReads($this->root()) as [$ns, $key, $where]) {
            if ($registry->get($ns, $key) === null) {
                $undeclared[] = "$ns.$key ($where)";
            }
        }

        $this->assertSame([], $undeclared, "Settings read but not declared:\n" . implode("\n", $undeclared));
    }

    /** Keys only the Nextcloud SQL views read are legacy: freeform, in the Legacy card. */
    public function test_keys_read_only_by_sql_views_stay_legacy(): void
    {
        self::bootKernel();
        $registry = self::getContainer()->get(SettingsRegistry::class);
        $root     = $this->root();
        $code     = array_map(fn($r) => "$r[0].$r[1]", $this->codeReads($root));
        $viewOnly = [];

        foreach ($this->viewReads($root) as [$ns, $key, $where]) {
            if (!in_array("$ns.$key", $code, true)) {
                $viewOnly["$ns.$key"] = [$ns, $key, $where];
            }
        }

        $this->assertArrayHasKey('bs.groupe_type.cda_id', $viewOnly);
        $this->assertArrayHasKey('bs.fonction.equipier_id', $viewOnly);

        $declared = [];
        foreach ($viewOnly as $id => [$ns, $key, $where]) {
            if ($registry->get($ns, $key) !== null) {
                $declared[] = "$id ($where)";
            }
        }

        $this->assertSame([], $declared, "Read only by SQL views, must stay legacy:\n" . implode("\n", $declared));
    }

    public function test_legacy_keys_are_not_declared(): void
    {
        self::bootKernel();
        $registry = self::getContainer()->get(SettingsRegistry::class);

        foreach ([['bs', 'facturation.coti_lvtx'], ['bs', 'usernames_cordelettes'], ['export', 'author']] as [$ns, $key]) {
            $this->assertNull($registry->get($ns, $key), "$ns.$key must stay in the Legacy card");
        }
    }

    /** @return list<array{string,string,string}> namespace, key, file:line */
    private function root(): string
    {
        return self::getContainer()->getParameter('kernel.project_dir');
    }

    private function codeReads(string $root): array
    {
        $reads = [];

        $code = (new Finder())->files()->in(["$root/src", "$root/netBS", "$root/templates"])->name(['*.php', '*.twig']);
        foreach ($code as $file) {
            foreach (self::CODE_PATTERNS as $pattern) {
                preg_match_all($pattern, $file->getContents(), $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
                foreach ($matches as $m) {
                    $reads[] = [$m[1][0], $m[2][0], $this->where($file, $m[0][1])];
                }
            }
        }

        return $reads;
    }

    private function viewReads(string $root): array
    {
        $reads = [];

        $views = (new Finder())->files()->in("$root/netBS/core/CoreBundle/Resources/sql/views")->name('*.sql');
        foreach ($views as $file) {
            preg_match_all(self::SQL_PATTERN, $file->getContents(), $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
            foreach ($matches as $m) {
                preg_match_all("/'([\w.]+)'/", $m[2][0], $keys);
                foreach ($keys[1] as $key) {
                    $reads[] = [$m[1][0], $key, $this->where($file, $m[0][1])];
                }
            }
        }

        return $reads;
    }

    private function where(\SplFileInfo $file, int $offset): string
    {
        $line = substr_count(file_get_contents($file->getPathname()), "\n", 0, $offset) + 1;

        return $file->getPathname() . ":" . $line;
    }
}
