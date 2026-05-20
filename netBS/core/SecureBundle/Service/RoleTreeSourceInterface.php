<?php

declare(strict_types=1);

namespace NetBS\SecureBundle\Service;

/**
 * Bundle-supplied source of role-tree definitions. Implementations are
 * tag-collected and processed by {@see RoleTreeSyncer} in {@see getOrder}
 * order — a source that requires an existing role row as its root parent
 * must run after the source that creates that root.
 *
 * Tag service id `netbs.role_tree_source`.
 */
interface RoleTreeSourceInterface
{
    /**
     * Absolute path to the YAML file describing this source's role subtree.
     * The file's top-level key must be `roles`, mapping role-name to
     * { poids, description?, children? } nodes.
     */
    public function getYamlPath(): string;

    /**
     * Name of the existing role under which the entire subtree of this
     * source should be grafted, or null if the subtree's top-level roles
     * are themselves root roles.
     *
     * The syncer will look up this role at the start of processing and
     * abort if it doesn't yet exist in the database.
     */
    public function getRootParent(): ?string;

    /**
     * Sort key. Sources with smaller order run first. Use this to ensure
     * any source whose {@see getRootParent} points at a role exists by
     * the time it runs.
     */
    public function getOrder(): int;
}
