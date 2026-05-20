<?php

declare(strict_types=1);

namespace NetBS\SecureBundle\Audit;

use NetBS\FichierBundle\Mapping\BaseFonction;
use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\SecureBundle\Mapping\BaseRole;
use NetBS\SecureBundle\Mapping\BaseUser;

enum Provenance: string
{
    case DIRECT_ROLE = 'direct_role';      // user.roles
    case FONCTION_ROLE = 'fonction_role';  // user.membre.activesAttributions[].fonction.roles
    case AUTORISATION = 'autorisation';    // user.autorisations[]
}

final readonly class AccessGrant
{
    /** @param BaseRole[] $roles  Recursive children already expanded. */
    public function __construct(
        public Provenance $provenance,
        public ?BaseFonction $sourceFonction,  // set for FONCTION_ROLE only
        public array $roles,
        public ?BaseGroupe $scope,             // null for DIRECT_ROLE (global)
        public ?int $sourceId = null,          // attribution.id / autorisation.id, for deep links
    ) {}
}

final readonly class UserAccessReport
{
    /** @param AccessGrant[] $grants */
    public function __construct(public BaseUser $user, public array $grants) {}
}

final readonly class ScopeAccessEntry
{
    /** @param AccessGrant[] $grants */
    public function __construct(public BaseUser $user, public array $grants) {}
}

final readonly class ScopeAccessReport
{
    /** @param ScopeAccessEntry[] $entries */
    public function __construct(
        public BaseGroupe|BaseRole $scope,
        public array $entries,
    ) {}
}
