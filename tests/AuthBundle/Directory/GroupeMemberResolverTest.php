<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Directory;

use App\Entity\BSGroupe;
use App\Entity\BSMembre;
use NetBS\AuthBundle\Directory\GroupeMemberResolver;
use NetBS\FichierBundle\Entity\Attribution;
use NetBS\FichierBundle\Entity\Fonction;
use App\Tests\Support\RealRuleEngineTrait;
use NetBS\CoreBundle\Rule\Rule;
use NetBS\CoreBundle\Rule\RuleCondition;
use NetBS\CoreBundle\Rule\RuleEvaluationException;
use NetBS\CoreBundle\Rule\RuleGroup;
use PHPUnit\Framework\TestCase;

/**
 * Tree: troupe T <- patrouille P <- sub-patrouille S.
 * T has membre 1; P has membre 2 (cp) and 3 (eclaireur); S has membre 4 (eclaireur).
 */
final class GroupeMemberResolverTest extends TestCase
{
    use RealRuleEngineTrait;

    private GroupeMemberResolver $resolver;
    private Fonction $eclaireur;
    private Fonction $cp;
    private BSGroupe $troupe;
    private BSGroupe $patrouille;
    private BSGroupe $sousPatrouille;
    /** @var array<int, BSMembre> */
    private array $membres = [];

    protected function setUp(): void
    {
        $this->resolver = new GroupeMemberResolver($this->fichierRuleEvaluator());
        $this->eclaireur = new Fonction();
        $this->setId($this->eclaireur, 1);
        $this->cp = new Fonction();
        $this->setId($this->cp, 2);

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

    public function testModes(): void
    {
        self::assertSame([1], $this->resolve('direct'));
        self::assertSame([1, 2, 3, 4], $this->resolve('tous'));
    }

    public function testInactiveDescendantAttributionsIgnored(): void
    {
        foreach ($this->patrouille->getAttributions() as $attribution) {
            if ($attribution->getMembre()->getId() === 3) {
                $attribution->setDateFin(new \DateTime('-1 day'));
            }
        }
        // A future attribution doesn't count either.
        $this->attribute($this->sousPatrouille, 5, $this->eclaireur)->setDateDebut(new \DateTime('+1 day'));

        self::assertSame([1, 2, 4], $this->resolve('tous'));
    }

    public function testParentCycleTerminates(): void
    {
        $this->sousPatrouille->addEnfant($this->troupe);

        self::assertSame([1, 2, 3, 4], $this->resolve('tous'));
    }

    public function testRuleMode(): void
    {
        self::assertSame([1, 2], $this->resolve('regle', $this->rule('attribution.fonctions', ['fonctions' => [2]])));
    }

    public function testRuleModeDepth(): void
    {
        self::assertSame([1, 2, 3], $this->resolve('regle', $this->rule('attribution.profondeur', ['op' => 'le', 'profondeur' => 1])));
        self::assertSame([1, 4], $this->resolve('regle', $this->rule('attribution.profondeur', ['op' => 'eq', 'profondeur' => 2])));
    }

    public function testRuleModeCycleTerminates(): void
    {
        $this->sousPatrouille->addEnfant($this->troupe);

        self::assertSame([1, 2, 3, 4], $this->resolve('regle', $this->rule('attribution.profondeur', ['op' => 'le', 'profondeur' => 20])));
    }

    public function testEmptyRuleKeepsOwnMembersOnly(): void
    {
        self::assertSame([1], $this->resolve('regle', null));
    }

    public function testRegleModeThrowsOnBrokenRule(): void
    {
        $this->expectException(RuleEvaluationException::class);
        $this->resolve('regle', Rule::expert('membre.getFamille().getNom() == "x"')->toJson());
    }

    private function rule(string $block, array $params): string
    {
        return Rule::blocks(new RuleGroup('and', [new RuleCondition($block, $params)]))->toJson();
    }

    /** @return int[] sorted */
    private function resolve(string $mode, ?string $rule = null): array
    {
        $ids = $this->resolver->membreIds($this->troupe, $mode, $rule);
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
