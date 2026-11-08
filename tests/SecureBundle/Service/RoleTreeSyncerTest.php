<?php

declare(strict_types=1);

namespace App\Tests\SecureBundle\Service;

use App\Entity\BSUser;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\SecureBundle\Service\RoleTreeSourceInterface;
use NetBS\SecureBundle\Service\RoleTreeSyncer;
use NetBS\SecureBundle\Service\SecureConfig;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Yaml\Yaml;

/**
 * RoleTreeSyncer and `netbs:roles:sync` against netbs5_test, inside a
 * transaction that tearDown rolls back. Role names are prefixed ROLE_ZZT_
 * so the assertions never depend on the production tree.
 */
final class RoleTreeSyncerTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private Connection $conn;
    /** @var string[] */
    private array $yamlFiles = [];

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->conn = $this->em->getConnection();
        $this->conn->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->conn->rollBack();
        foreach ($this->yamlFiles as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    // --- creation / update ------------------------------------------------

    public function testCreatesMissingRolesWithTheirParents(): void
    {
        $report = $this->sync($this->source($this->tree()));

        $this->assertEqualsCanonicalizing(['ROLE_ZZT_ROOT', 'ROLE_ZZT_CHILD', 'ROLE_ZZT_LEAF'], $report->created);
        $this->assertNull($this->parentOf('ROLE_ZZT_ROOT'));
        $this->assertSame('ROLE_ZZT_ROOT', $this->parentOf('ROLE_ZZT_CHILD'));
        $this->assertSame('ROLE_ZZT_CHILD', $this->parentOf('ROLE_ZZT_LEAF'));
        $this->assertSame(10, (int) $this->row('ROLE_ZZT_LEAF')['poids']);
    }

    public function testSecondRunChangesNothing(): void
    {
        $source = $this->source($this->tree());
        $this->sync($source);
        $before = $this->snapshot();

        $report = $this->sync($source);

        $this->assertSame([], $report->created);
        $this->assertSame([], $report->updated);
        $this->assertSame(0, $report->dedupedFrom);
        $this->assertCount(3, $report->unchanged);
        $this->assertSame($before, $this->snapshot());
    }

    public function testFixesParentPoidsAndDescriptionInPlace(): void
    {
        $other = $this->insertRole('ROLE_ZZT_OTHER');
        $this->insertRole('ROLE_ZZT_ROOT', 100);
        $childId = $this->insertRole('ROLE_ZZT_CHILD', 1, 'stale', $other);
        $userId = $this->insertUser('zzt_fix');
        $this->assign('bsuser_role', 'bsuser_id', $userId, $childId);

        $report = $this->sync($this->source($this->tree()));

        $this->assertContains('ROLE_ZZT_CHILD', $report->updated);
        $child = $this->row('ROLE_ZZT_CHILD');
        $this->assertSame($childId, (int) $child['id'], 'updated in place, not recreated');
        $this->assertSame('ROLE_ZZT_ROOT', $this->parentOf('ROLE_ZZT_CHILD'));
        $this->assertSame(50, (int) $child['poids']);
        $this->assertSame('child', $child['description']);
        $this->assertSame([$childId], $this->rolesOf('bsuser_role', 'bsuser_id', $userId));
    }

    public function testMovesATopLevelRoleBackToTheRootAndKeepsItsAssignments(): void
    {
        $other = $this->insertRole('ROLE_ZZT_OTHER');
        $rootId = $this->insertRole('ROLE_ZZT_ROOT', 100, 'root', $other);
        $userId = $this->insertUser('zzt_reroot');
        $this->assign('bsuser_role', 'bsuser_id', $userId, $rootId);

        $report = $this->sync($this->source($this->tree()));

        $this->assertContains('ROLE_ZZT_ROOT', $report->updated);
        $this->assertNull($this->parentOf('ROLE_ZZT_ROOT'));
        $this->assertSame($rootId, (int) $this->row('ROLE_ZZT_ROOT')['id']);
        $this->assertSame([$rootId], $this->rolesOf('bsuser_role', 'bsuser_id', $userId));
        $this->assertNotNull($this->row('ROLE_ZZT_OTHER'), 'the former parent is left alone');
    }

    public function testGraftsSubtreeUnderTheRootParentOfLaterSources(): void
    {
        $base = $this->source(['ROLE_ZZT_BASE' => ['poids' => 1]], null, 1);
        $graft = $this->source(['ROLE_ZZT_GRAFT' => ['poids' => 2]], 'ROLE_ZZT_BASE', 2);

        // Passed out of order: getOrder() decides.
        $this->sync($graft, $base);

        $this->assertSame('ROLE_ZZT_BASE', $this->parentOf('ROLE_ZZT_GRAFT'));
    }

    public function testMissingRootParentAborts(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->sync($this->source(['ROLE_ZZT_GRAFT' => ['poids' => 2]], 'ROLE_ZZT_NOWHERE', 1));
    }

    // --- dedupe -----------------------------------------------------------

    public function testDedupeKeepsTheOldestRowAndMovesEveryAssignmentOntoIt(): void
    {
        $keeper = $this->insertRole('ROLE_ZZT_DUP');
        $dup = $this->insertRole('ROLE_ZZT_DUP');
        $kid = $this->insertRole('ROLE_ZZT_KID', 0, '', $dup);

        $userOnDup = $this->insertUser('zzt_dup');
        $userOnBoth = $this->insertUser('zzt_both');
        $fonction = $this->insertFonction();
        $autorisation = $this->insertAutorisation();

        $this->assign('bsuser_role', 'bsuser_id', $userOnDup, $dup);
        $this->assign('bsuser_role', 'bsuser_id', $userOnBoth, $keeper);
        $this->assign('bsuser_role', 'bsuser_id', $userOnBoth, $dup);
        $this->assign('fonction_role', 'fonction_id', $fonction, $dup);
        $this->assign('autorisation_role', 'autorisation_id', $autorisation, $dup);

        $report = $this->sync($this->source(['ROLE_ZZT_UNRELATED' => ['poids' => 1]]));

        $this->assertGreaterThanOrEqual(1, $report->dedupedFrom);
        $this->assertSame([$keeper], $this->idsOf('ROLE_ZZT_DUP'));
        $this->assertSame([$kid], $this->idsOf('ROLE_ZZT_KID'), 'children of the duplicate survive');
        $this->assertSame('ROLE_ZZT_DUP', $this->parentOf('ROLE_ZZT_KID'));
        $this->assertSame([$keeper], $this->rolesOf('bsuser_role', 'bsuser_id', $userOnDup));
        $this->assertSame([$keeper], $this->rolesOf('bsuser_role', 'bsuser_id', $userOnBoth));
        $this->assertSame([$keeper], $this->rolesOf('fonction_role', 'fonction_id', $fonction));
        $this->assertSame([$keeper], $this->rolesOf('autorisation_role', 'autorisation_id', $autorisation));
    }

    public function testDedupedDuplicateIsThenSyncedLikeAnyOtherRole(): void
    {
        $keeper = $this->insertRole('ROLE_ZZT_ROOT');
        $this->insertRole('ROLE_ZZT_ROOT');
        $this->insertRole('ROLE_ZZT_CHILD');
        $this->insertRole('ROLE_ZZT_CHILD');

        $source = $this->source($this->tree());
        $this->sync($source);

        $this->assertSame([$keeper], $this->idsOf('ROLE_ZZT_ROOT'));
        $this->assertCount(1, $this->idsOf('ROLE_ZZT_CHILD'));
        $this->assertSame('ROLE_ZZT_ROOT', $this->parentOf('ROLE_ZZT_CHILD'));

        $before = $this->snapshot();
        $report = $this->sync($source);
        $this->assertSame(0, $report->dedupedFrom);
        $this->assertSame($before, $this->snapshot());
    }

    // --- roles absent from the YAML ---------------------------------------

    public function testNeverDropsAnAssignedRoleMissingFromTheYaml(): void
    {
        $orphan = $this->insertRole('ROLE_ZZT_ORPHAN');
        $userId = $this->insertUser('zzt_orphan');
        $fonction = $this->insertFonction();
        $this->assign('bsuser_role', 'bsuser_id', $userId, $orphan);
        $this->assign('fonction_role', 'fonction_id', $fonction, $orphan);

        $this->sync($this->source($this->tree()));

        $this->assertSame([$orphan], $this->idsOf('ROLE_ZZT_ORPHAN'));
        $this->assertSame([$orphan], $this->rolesOf('bsuser_role', 'bsuser_id', $userId));
        $this->assertSame([$orphan], $this->rolesOf('fonction_role', 'fonction_id', $fonction));
    }

    // --- command ----------------------------------------------------------

    public function testCommandIsIdempotentAndKeepsAssignedOrphans(): void
    {
        $orphan = $this->insertRole('ROLE_ZZT_ORPHAN');
        $userId = $this->insertUser('zzt_cmd');
        $this->assign('bsuser_role', 'bsuser_id', $userId, $orphan);

        $tester = new CommandTester((new Application(self::$kernel))->find('netbs:roles:sync'));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $afterFirst = $this->snapshot();
        $this->assertSame(Command::SUCCESS, $tester->execute([]));

        $this->assertSame($afterFirst, $this->snapshot());
        $this->assertSame(
            0,
            (int) $this->conn->fetchOne('SELECT COUNT(*) - COUNT(DISTINCT role) FROM netbs_secure_roles'),
            'no duplicate names left',
        );
        $this->assertNotNull($this->row('ROLE_ADMIN'));
        $this->assertSame([$orphan], $this->rolesOf('bsuser_role', 'bsuser_id', $userId));
    }

    // --- helpers ----------------------------------------------------------

    private function tree(): array
    {
        return [
            'ROLE_ZZT_ROOT' => [
                'poids' => 100,
                'description' => 'root',
                'children' => [
                    'ROLE_ZZT_CHILD' => [
                        'poids' => 50,
                        'description' => 'child',
                        'children' => [
                            'ROLE_ZZT_LEAF' => ['poids' => 10, 'description' => 'leaf'],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function source(array $roles, ?string $rootParent = null, int $order = 1): RoleTreeSourceInterface
    {
        $path = tempnam(sys_get_temp_dir(), 'roles_') . '.yml';
        file_put_contents($path, Yaml::dump(['roles' => $roles], 10));
        $this->yamlFiles[] = $path;

        return new class ($path, $rootParent, $order) implements RoleTreeSourceInterface {
            public function __construct(private string $path, private ?string $rootParent, private int $order)
            {
            }

            public function getYamlPath(): string
            {
                return $this->path;
            }

            public function getRootParent(): ?string
            {
                return $this->rootParent;
            }

            public function getOrder(): int
            {
                return $this->order;
            }
        };
    }

    private function sync(RoleTreeSourceInterface ...$sources): \NetBS\SecureBundle\Service\RoleSyncReport
    {
        $syncer = new RoleTreeSyncer($this->em, static::getContainer()->get(SecureConfig::class), $sources);

        return $syncer->syncAll();
    }

    private function insertRole(string $name, int $poids = 0, string $description = '', ?int $parentId = null): int
    {
        $this->conn->insert('netbs_secure_roles', [
            'role' => $name, 'poids' => $poids, 'description' => $description, 'parent_id' => $parentId,
        ]);

        return (int) $this->conn->lastInsertId();
    }

    private function insertUser(string $username): int
    {
        $user = new BSUser();
        $user->setUsername($username . '_' . bin2hex(random_bytes(4)));
        $user->setPassword('placeholder-hash');
        $user->setIsActive(true);
        $this->em->persist($user);
        $this->em->flush();

        return (int) $user->getId();
    }

    private function insertFonction(): int
    {
        $this->conn->insert('netbs_fichier_fonctions', ['nom' => 'ZZT', 'abbreviation' => 'ZZT']);

        return (int) $this->conn->lastInsertId();
    }

    private function insertAutorisation(): int
    {
        $this->conn->insert('netbs_secure_autorisations', ['user_id' => null, 'groupe_id' => null]);

        return (int) $this->conn->lastInsertId();
    }

    private function assign(string $table, string $subjectCol, int $subjectId, int $roleId): void
    {
        $this->conn->insert($table, [$subjectCol => $subjectId, 'role_id' => $roleId]);
    }

    /** @return int[] */
    private function rolesOf(string $table, string $subjectCol, int $subjectId): array
    {
        return array_map('intval', $this->conn->fetchFirstColumn(
            "SELECT role_id FROM $table WHERE $subjectCol = ? ORDER BY role_id",
            [$subjectId],
        ));
    }

    /** @return int[] */
    private function idsOf(string $name): array
    {
        return array_map('intval', $this->conn->fetchFirstColumn(
            'SELECT id FROM netbs_secure_roles WHERE role = ? ORDER BY id',
            [$name],
        ));
    }

    private function row(string $name): ?array
    {
        return $this->conn->fetchAssociative('SELECT * FROM netbs_secure_roles WHERE role = ?', [$name]) ?: null;
    }

    private function parentOf(string $name): ?string
    {
        $parent = $this->conn->fetchOne(
            'SELECT p.role FROM netbs_secure_roles r LEFT JOIN netbs_secure_roles p ON p.id = r.parent_id WHERE r.role = ?',
            [$name],
        );

        return $parent === false ? null : $parent;
    }

    private function snapshot(): array
    {
        return $this->conn->fetchAllAssociative(
            'SELECT id, role, poids, description, parent_id FROM netbs_secure_roles ORDER BY id',
        );
    }
}
