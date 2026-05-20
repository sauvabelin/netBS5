<?php

declare(strict_types=1);

namespace NetBS\SecureBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\SecureBundle\Entity\Role;
use NetBS\SecureBundle\Mapping\BaseRole;
use Symfony\Component\Yaml\Yaml;

/**
 * Reconciles the role tree in `netbs_secure_roles` with YAML definitions
 * supplied by {@see RoleTreeSourceInterface} services tagged
 * `netbs.role_tree_source`.
 *
 * Behaviour for each role in each source's YAML:
 *  - Looked up by name; if missing, created.
 *  - If present, `poids`, `description`, and `parent` are reset to match.
 *  - Children recurse with the just-upserted node as their parent.
 *
 * Roles that exist in the DB but not in any YAML are left alone (the
 * command surfaces them as orphans but does not delete them — anyone
 * could have added them through the admin UI).
 *
 * Touches only `netbs_secure_roles`. Never reads or writes user, fonction,
 * membre, autorisation, or any other table.
 */
final class RoleTreeSyncer
{
    /** @var iterable<RoleTreeSourceInterface> */
    private iterable $sources;

    /**
     * @param iterable<RoleTreeSourceInterface> $sources Tag-collected role sources.
     */
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SecureConfig $config,
        iterable $sources,
    ) {
        $this->sources = $sources;
    }

    public function syncAll(): RoleSyncReport
    {
        $sorted = is_array($this->sources) ? $this->sources : iterator_to_array($this->sources, false);
        usort($sorted, static fn (RoleTreeSourceInterface $a, RoleTreeSourceInterface $b) => $a->getOrder() <=> $b->getOrder());

        $report = new RoleSyncReport();
        $repo = $this->em->getRepository(Role::class);

        $connection = $this->em->getConnection();
        $connection->beginTransaction();

        try {
            // Self-heal first: collapse any duplicate role rows that share a name.
            // Idempotent — does nothing on an already-clean DB.
            $report->dedupedFrom += $this->dedupeRoleRowsByName();

            foreach ($sorted as $source) {
                $path = $source->getYamlPath();
                $parsed = Yaml::parseFile($path);

                if (!is_array($parsed) || !array_key_exists('roles', $parsed) || !is_array($parsed['roles'])) {
                    throw new \RuntimeException(sprintf(
                        'Cannot sync %s (%s): YAML must be a mapping with a top-level "roles:" key whose value is a mapping. '
                        . 'Got %s.',
                        $source::class,
                        $path,
                        is_array($parsed) ? 'no "roles" key (or non-array value)' : 'non-array root'
                    ));
                }

                $data = $parsed['roles'];

                $rootParent = null;
                if ($source->getRootParent() !== null) {
                    $rootParent = $repo->findOneBy(['role' => $source->getRootParent()]);
                    if ($rootParent === null) {
                        throw new \RuntimeException(sprintf(
                            'Cannot sync %s: required root parent role "%s" not found in database. '
                            . 'Ensure the source that creates it (lower getOrder) runs first.',
                            $path,
                            $source->getRootParent()
                        ));
                    }
                }

                $this->syncTree($data, $rootParent, $report);
                $this->em->flush();
            }

            $connection->commit();
        } catch (\Throwable $e) {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            throw $e;
        }

        return $report;
    }

    /**
     * @param array<string, array{poids?: int, description?: string, children?: array}> $data
     */
    private function syncTree(array $data, ?BaseRole $parent, RoleSyncReport $report): void
    {
        $roleClass = $this->config->getRoleClass();
        $repo = $this->em->getRepository(Role::class);

        foreach ($data as $name => $params) {
            $poids = $params['poids'] ?? 0;
            $description = $params['description'] ?? '';

            $role = $repo->findOneBy(['role' => $name]);
            if ($role === null) {
                $role = new $roleClass($name, $poids, $description);
                $this->em->persist($role);
                $report->created[] = $name;
            } else {
                $changed = false;
                if ($role->getPoids() !== $poids) {
                    $role->setPoids($poids);
                    $changed = true;
                }
                if ($role->getDescription() !== $description) {
                    $role->setDescription($description);
                    $changed = true;
                }
                if ($parent !== null && ($role->getParent()?->getRole() !== $parent->getRole())) {
                    $role->setParent($parent);
                    $changed = true;
                }
                if ($changed) {
                    $report->updated[] = $name;
                } else {
                    $report->unchanged[] = $name;
                }
            }

            if ($parent !== null && $role->getParent() === null) {
                // First-insert path: setParent here so child recursion sees the right anchor.
                $role->setParent($parent);
            }

            if (!empty($params['children'])) {
                $this->syncTree($params['children'], $role, $report);
            }
        }
    }

    /**
     * Collapse duplicate role rows that share the same `role` name. For each
     * such name the row with the smallest id wins; every foreign-key pointer
     * (bsuser_role, fonction_role, autorisation_role, netbs_secure_roles.parent_id)
     * to a non-canonical row is redirected to the canonical row, then the
     * non-canonical rows are deleted.
     *
     * Returns the number of rows removed. Always safe to run — does nothing
     * on a DB that already has no duplicates.
     */
    private function dedupeRoleRowsByName(): int
    {
        $conn = $this->em->getConnection();

        // 1. Redirect bsuser_role pointers (INSERT IGNORE + DELETE to avoid PK collisions).
        foreach (['bsuser_role' => 'bsuser_id', 'fonction_role' => 'fonction_id', 'autorisation_role' => 'autorisation_id'] as $table => $subjectCol) {
            $conn->executeStatement("
                INSERT IGNORE INTO $table ($subjectCol, role_id)
                SELECT j.$subjectCol, k.keeper_id
                FROM $table j
                JOIN netbs_secure_roles dup ON dup.id = j.role_id
                JOIN (SELECT role, MIN(id) AS keeper_id FROM netbs_secure_roles GROUP BY role) k
                     ON k.role = dup.role
                WHERE dup.id <> k.keeper_id
            ");
            $conn->executeStatement("
                DELETE j FROM $table j
                JOIN netbs_secure_roles dup ON dup.id = j.role_id
                JOIN (SELECT role, MIN(id) AS keeper_id FROM netbs_secure_roles GROUP BY role) k
                     ON k.role = dup.role
                WHERE dup.id <> k.keeper_id
            ");
        }

        // 2. Redirect parent_id pointers off duplicates so the tree stays intact.
        $conn->executeStatement("
            UPDATE netbs_secure_roles r
            JOIN netbs_secure_roles dup ON dup.id = r.parent_id
            JOIN (SELECT role, MIN(id) AS keeper_id FROM netbs_secure_roles GROUP BY role) k
                 ON k.role = dup.role
            SET r.parent_id = k.keeper_id
            WHERE dup.id <> k.keeper_id
        ");

        // 3. Delete the duplicate rows.
        $deleted = (int) $conn->executeStatement("
            DELETE r FROM netbs_secure_roles r
            JOIN (SELECT role, MIN(id) AS keeper_id FROM netbs_secure_roles GROUP BY role) k
                 ON k.role = r.role
            WHERE r.id <> k.keeper_id
        ");

        // Tell Doctrine its in-memory Role identity map may be stale.
        $this->em->clear(Role::class);

        return $deleted;
    }
}
