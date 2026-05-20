<?php

declare(strict_types=1);

namespace NetBS\SecureBundle\Audit;

use NetBS\FichierBundle\Mapping\BaseFonction;
use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\SecureBundle\Mapping\BaseRole;

final readonly class AccessGrant
{
    /**
     * @param BaseRole[] $roles               Recursive children already expanded.
     * @param string[]   $explicitRoleNames   Names of the roles in $roles that are literally
     *                                        held on this path (i.e. were in the source collection,
     *                                        not gained by tree expansion). Roles in $roles whose
     *                                        names are NOT in this set are inherited.
     */
    public function __construct(
        public Provenance $provenance,
        public ?BaseFonction $sourceFonction,  // set for FONCTION_ROLE only
        public array $roles,
        public array $explicitRoleNames,
        public ?BaseGroupe $scope,             // null for DIRECT_ROLE (global)
        public ?int $sourceId = null,          // attribution.id / autorisation.id, for deep links
    ) {}
}
