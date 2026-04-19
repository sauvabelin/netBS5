<?php

namespace NetBS\FichierBundle\Service;

use NetBS\FichierBundle\Mapping\BaseGroupe;

final class MesUnitesRoot
{
    /**
     * @param MesUnitesChild[] $children
     */
    public function __construct(
        public readonly BaseGroupe $group,
        public readonly int $totalActiveMembers,
        public readonly array $children,
    ) {}
}
