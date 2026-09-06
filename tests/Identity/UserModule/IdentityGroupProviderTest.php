<?php

declare(strict_types=1);

namespace App\Tests\Identity\UserModule;

use App\Entity\BSUser;
use App\Identity\UserModule\IdentityGroupProvider;
use NetBS\FichierBundle\Mapping\BaseAttribution;
use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\FichierBundle\Mapping\BaseMembre;
use PHPUnit\Framework\TestCase;

/**
 * Covers IdentityGroupProvider's narrowing + Membre traversal:
 *   - The contract is typed `object` so AuthBundle stays free of BSUser; the
 *     provider must narrow at runtime and return [] for anything else.
 *   - A BSUser with no attached Membre yields no groups.
 *   - Duplicate group names (the same group appearing in several active
 *     attributions, e.g. multiple fonctions in the same unit) collapse to a
 *     single entry — downstream consumers expect a deduplicated list.
 */
final class IdentityGroupProviderTest extends TestCase
{
    public function testGroupProviderReturnsEmptyForNonBSUserObject(): void
    {
        // Defensive: contract is typed `object` to keep AuthBundle free of
        // BSUser. The concrete provider must narrow at runtime.
        $provider = new IdentityGroupProvider();
        $this->assertSame([], $provider->groupsFor(new \stdClass()));
    }

    public function testGroupsForBSUserWithNoMembreReturnsEmpty(): void
    {
        $user = $this->createMock(BSUser::class);
        $user->method('getMembre')->willReturn(null);

        $provider = new IdentityGroupProvider();
        $this->assertSame([], $provider->groupsFor($user));
    }

    public function testGroupsForBSUserDeduplicatesGroupNames(): void
    {
        // Two active attributions point at distinct Groupe instances that
        // happen to share the same `nom` (e.g. a member holding two fonctions
        // in the same unit). The provider must collapse to one entry, in
        // first-seen order.
        $groupeA1 = $this->makeGroupe('Patrouille');
        $groupeA2 = $this->makeGroupe('Patrouille');
        $groupeB  = $this->makeGroupe('Section');

        $membre = $this->createMock(BaseMembre::class);
        $membre->method('getActivesAttributions')->willReturn([
            $this->makeAttribution($groupeA1),
            $this->makeAttribution($groupeB),
            $this->makeAttribution($groupeA2),
        ]);

        $user = $this->createMock(BSUser::class);
        $user->method('getMembre')->willReturn($membre);

        $provider = new IdentityGroupProvider();
        $groups = $provider->groupsFor($user);

        $this->assertSame(['Patrouille', 'Section'], $groups);
    }

    private function makeGroupe(string $name): BaseGroupe
    {
        $groupe = $this->createMock(BaseGroupe::class);
        $groupe->method('getNom')->willReturn($name);
        return $groupe;
    }

    private function makeAttribution(BaseGroupe $groupe): BaseAttribution
    {
        $attribution = $this->createMock(BaseAttribution::class);
        $attribution->method('getGroupe')->willReturn($groupe);
        return $attribution;
    }
}
