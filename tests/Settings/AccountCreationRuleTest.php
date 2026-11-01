<?php

declare(strict_types=1);

namespace App\Tests\Settings;

use App\Subscriber\DoctrineUserAccountSubscriber;
use App\Tests\Support\InMemoryFichierTrait;
use App\Tests\Support\RealRuleEngineTrait;
use NetBS\CoreBundle\Rule\Rule;
use NetBS\CoreBundle\Rule\RuleCondition;
use NetBS\CoreBundle\Rule\RuleGroup;
use NetBS\CoreBundle\Service\ParameterManager;
use NetBS\FichierBundle\Mapping\BaseMembre;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AccountCreationRuleTest extends TestCase
{
    use InMemoryFichierTrait, RealRuleEngineTrait;

    private function subscriber(?string $rule): DoctrineUserAccountSubscriber
    {
        $params = $this->createMock(ParameterManager::class);
        $params->method('getValue')->willReturnMap([
            ['bs', 'comptes.creation', true, $rule],
            ['bs', 'groupe.adabs_id', true, '50'],
        ]);
        $params->method('getStoredValue')->willReturnMap([['bs', 'fonction.weight.user_account', '100']]);

        return new DoctrineUserAccountSubscriber(
            $this->createMock(UserPasswordHasherInterface::class),
            $this->createMock(MailerInterface::class),
            new RequestStack(),
            $params,
            new NullLogger(),
            $this->fichierRuleEvaluator(),
        );
    }

    public function test_without_rule_uses_weight_and_adabs(): void
    {
        $membre = $this->membre(1);
        $membre->setStatut(BaseMembre::INSCRIT);
        $chef   = $this->attribute($membre, $this->groupe(1), $this->fonction(1, 150));
        $this->assertTrue($this->subscriber(null)->shouldCreateAccount($chef));

        $light  = $this->attribute($membre, $this->groupe(2), $this->fonction(2, 10));
        $this->assertFalse($this->subscriber(null)->shouldCreateAccount($light), 'only the saved attribution counts');

        $this->attribute($membre, $this->groupe(50), $this->fonction(3, 0));
        $this->assertFalse($this->subscriber(null)->shouldCreateAccount($chef), 'ADABS members get no account');
    }

    public function test_account_created_when_rule_matches(): void
    {
        $membre = $this->membre(1);
        $membre->setStatut(BaseMembre::INSCRIT);
        $attribution = $this->attribute($membre, $this->groupe(7), $this->fonction(1, 0));
        $rule = Rule::blocks(new RuleGroup('and', [new RuleCondition('membre.attribution_groupes', ['groupes' => [7], 'sous_groupes' => 'aucun'])]))->toJson();

        $this->assertTrue($this->subscriber($rule)->shouldCreateAccount($attribution));
    }

    public function test_no_account_when_rule_fails(): void
    {
        $membre = $this->membre(1);
        $membre->setStatut(BaseMembre::INSCRIT);
        $attribution = $this->attribute($membre, $this->groupe(7), $this->fonction(1, 500));
        $rule = Rule::blocks(new RuleGroup('and', [new RuleCondition('membre.fonction_poids', ['op' => 'gt', 'poids' => 900])]))->toJson();

        $this->assertFalse($this->subscriber($rule)->shouldCreateAccount($attribution));
    }

    public function test_non_inscrit_never_gets_account(): void
    {
        $membre = $this->membre(1);
        $membre->setStatut(BaseMembre::DESINSCRIT);
        $attribution = $this->attribute($membre, $this->groupe(7), $this->fonction(1, 500));

        $this->assertFalse($this->subscriber(Rule::expert('true')->toJson())->shouldCreateAccount($attribution));
    }

    public function test_rule_sees_the_attribution_being_saved(): void
    {
        // AttributionController / MassAdderController only call setMembre(); the membre's
        // collection may already be loaded without the new attribution.
        $membre = $this->membre(1);
        $membre->setStatut(BaseMembre::INSCRIT);
        $membre->getActivesAttributions();
        $attribution = new \NetBS\FichierBundle\Entity\Attribution();
        $attribution->setFonction($this->fonction(1, 60));
        $attribution->setDateDebut(new \DateTime('-1 day'));
        $attribution->setMembre($membre);
        $this->groupe(7)->addAttribution($attribution);
        $rule = Rule::blocks(new RuleGroup('and', [new RuleCondition('membre.fonction_poids', ['op' => 'ge', 'poids' => 50])]))->toJson();

        $this->assertTrue($this->subscriber($rule)->shouldCreateAccount($attribution));
    }

}
