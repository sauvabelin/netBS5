<?php

declare(strict_types=1);

namespace NetBS\SecureBundle\Audit;

use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\SecureBundle\Mapping\BaseRole;

final readonly class ScopeAccessReport
{
    /** @param ScopeAccessEntry[] $entries */
    public function __construct(
        public BaseGroupe|BaseRole $scope,
        public array $entries,
    ) {}
}
