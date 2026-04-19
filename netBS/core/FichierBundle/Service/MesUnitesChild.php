<?php

namespace NetBS\FichierBundle\Service;

use NetBS\FichierBundle\Mapping\BaseGroupe;

final class MesUnitesChild
{
    public function __construct(
        public readonly BaseGroupe $group,
        public readonly int $activeMembers,
        public readonly ?string $userFonction,
    ) {}
}
