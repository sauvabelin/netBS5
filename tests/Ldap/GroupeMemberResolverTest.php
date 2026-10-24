<?php

declare(strict_types=1);

namespace App\Tests\Ldap;

use App\Entity\BSGroupe;
use App\Entity\BSMembre;
use App\Ldap\GroupeMemberResolver;
use NetBS\FichierBundle\Entity\Attribution;
use NetBS\FichierBundle\Entity\Fonction;
use PHPUnit\Framework\TestCase;

/**
 * Tree: troupe T <- patrouille P <- sub-patrouille S.
 * T has membre 1; P has membre 2 (cp) and 3 (eclaireur); S has membre 4 (eclaireur).
 */
final class GroupeMemberResolverTest extends TestCase
{
    private GroupeMemberResolver $resolver;
    private Fonction $cp;
    private Fonction $eclaireur;
    private BSGroupe $troupe;
    private BSGroupe $patrouille;
    private BSGroupe $sousPatrouille;
    /** @var array<int, BSMembre> */
    private array $membres = [];

    protected function setUp(): void
    {
        $this->resolver = new GroupeMemberResolver();
        $this->cp = (new Fonction())->setCompteDansParent(true);
        $this->eclaireur = new Fonction();

        $this->troupe = $this->groupe(1);
        $this->patrouille = $this->groupe(2);
        $this->sousPatrouille = $this->groupe(3);
        $this->troupe->addEnfant($this->patrouille);
        $this->patrouille->addEnfant($this->sousPatrouille);

        $this->attribute($this->troupe, 1, $this->eclaireur);
        $this->attribute($this->patrouille, 2, $this->cp);
        $this->attribute($this->patrouille, 3, $this->eclaireur);
        $this->attribute($this->sousPatrouille, 4, $this->eclaireur);
    }

    public function testDirectOnlyReturnsDirectAttributions(): void
    {
        self::assertSame([1], $this->resolve('direct'));
    }

    public function testTousIncludesAllDescendants(): void
    {
        self::assertSame([1, 2, 3, 4], $this->resolve('tous'));
    }

    public function testFonctionsIncludesOnlyMarkedFonctions(): void
    {
        self::assertSame([1, 2], $this->resolve('fonctions'));
    }

    public function testInactiveDescendantAttributionsIgnored(): void
    {
        foreach ($this->patrouille->getAttributions() as $attribution) {
            if ($attribution->getMembre()->getId() === 3) {
                $attribution->setDateFin(new \DateTime('-1 day'));
            }
        }
        // A future attribution doesn't count either.
        $this->attribute($this->sousPatrouille, 5, $this->cp)->setDateDebut(new \DateTime('+1 day'));

        self::assertSame([1, 2, 4], $this->resolve('tous'));
        self::assertSame([1, 2], $this->resolve('fonctions'));
    }

    public function testMembreCountedOnce(): void
    {
        $this->attribute($this->patrouille, 1, $this->cp);
        $this->attribute($this->sousPatrouille, 2, $this->eclaireur);

        $ids = $this->resolver->membreIds($this->troupe, 'tous');
        self::assertSame(array_values(array_unique($ids)), $ids);
        self::assertSame([1, 2, 3, 4], $this->resolve('tous'));
    }

    public function testParentCycleTerminates(): void
    {
        $this->sousPatrouille->addEnfant($this->troupe);

        self::assertSame([1, 2, 3, 4], $this->resolve('tous'));
    }

    public function testUnknownModeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->resolver->membreIds($this->troupe, 'enfants');
    }

    /** @return int[] sorted */
    private function resolve(string $mode): array
    {
        $ids = $this->resolver->membreIds($this->troupe, $mode);
        sort($ids);
        return $ids;
    }

    private function groupe(int $id): BSGroupe
    {
        $groupe = new BSGroupe();
        $this->setId($groupe, $id);
        return $groupe;
    }

    private function attribute(BSGroupe $groupe, int $membreId, Fonction $fonction): Attribution
    {
        if (!isset($this->membres[$membreId])) {
            $this->membres[$membreId] = new BSMembre();
            $this->setId($this->membres[$membreId], $membreId);
        }

        $attribution = new Attribution();
        $attribution->setMembre($this->membres[$membreId]);
        $attribution->setFonction($fonction);
        $attribution->setDateDebut(new \DateTime('-1 day'));
        $groupe->addAttribution($attribution);
        return $attribution;
    }

    private function setId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setAccessible(true);
        $property->setValue($entity, $id);
    }
}
