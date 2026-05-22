<?php

declare(strict_types=1);

namespace NetBS\SecureBundle\Audit;

enum Provenance: string
{
    case DIRECT_ROLE = 'direct_role';      // user.roles
    case FONCTION_ROLE = 'fonction_role';  // user.membre.activesAttributions[].fonction.roles
    case AUTORISATION = 'autorisation';    // user.autorisations[]
}
