<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Directory;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\AuthBundle\Entity\DirectoryComputedGroup;
use NetBS\AuthBundle\Entity\DirectoryPublicationRule;
use NetBS\AuthBundle\Rule\DirectoryRuleLabeler;
use NetBS\CoreBundle\Entity\StoredRule;
use NetBS\CoreBundle\Rule\Rule;
use NetBS\CoreBundle\Rule\RuleCondition;
use NetBS\CoreBundle\Rule\RuleGroup;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Runs against netbs5_test, inside a transaction that tearDown rolls back.
 */
final class DirectoryRuleEntitiesTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ValidatorInterface $validator;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->validator = static::getContainer()->get(ValidatorInterface::class);
        $this->em->getConnection()->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->rollBack();
        parent::tearDown();
    }

    private static function json(string $block, array $params): string
    {
        return Rule::blocks(new RuleGroup('and', [new RuleCondition($block, $params)]))->toJson();
    }

    private function publicationRule(): DirectoryPublicationRule
    {
        $rule = new DirectoryPublicationRule();
        $rule->setLabel('Unités');
        $rule->setPosition(1);
        $rule->setSelectionJson(self::json('groupe.types', ['types' => [1]]));

        return $rule;
    }

    private function computedGroup(): DirectoryComputedGroup
    {
        $group = new DirectoryComputedGroup();
        $group->setName('chefs');
        $group->setMembresJson(self::json('membre.fonction_poids', ['op' => 'ge', 'value' => 50]));

        return $group;
    }

    /** @return string[] property paths of the violations */
    private function violations(object $entity): array
    {
        $paths = [];
        foreach ($this->validator->validate($entity) as $violation) {
            $paths[] = $violation->getPropertyPath();
        }

        return $paths;
    }

    private function storedRuleCount(): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM netbs_core_rules');
    }

    public function testPublicationRulePersistsWithBothRules(): void
    {
        $rule = $this->publicationRule();
        $rule->setSousGroupes(DirectoryPublicationRule::SOUS_GROUPES_REGLE);
        $rule->setSousGroupesRuleJson(self::json('attribution.profondeur', ['op' => 'le', 'value' => 1]));
        $this->em->persist($rule);
        $this->em->flush();
        $this->em->clear();

        $loaded = $this->em->find(DirectoryPublicationRule::class, $rule->getId());

        $this->assertSame('Unités', $loaded->getLabel());
        $this->assertSame(1, $loaded->getPosition());
        $this->assertSame('groupe', $loaded->getSelection()->getContext());
        $this->assertSame(self::json('groupe.types', ['types' => [1]]), $loaded->getSelectionJson());
        $this->assertSame(DirectoryPublicationRule::SOUS_GROUPES_REGLE, $loaded->getSousGroupes());
        $this->assertSame('attribution_sous_groupe', $loaded->getSousGroupesRule()->getContext());
    }

    public function testEmptySelectionIsInvalid(): void
    {
        $rule = $this->publicationRule();
        $rule->setSelectionJson(Rule::empty()->toJson());

        $this->assertContains('selectionJson', $this->violations($rule));
        $this->assertSame([], $this->violations($this->publicationRule()));
    }

    public function testRegleModeNeedsRule(): void
    {
        $rule = $this->publicationRule();
        $rule->setSousGroupes(DirectoryPublicationRule::SOUS_GROUPES_REGLE);

        $this->assertContains('sousGroupesRuleJson', $this->violations($rule));
    }

    public function testUnknownModeIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->publicationRule()->setSousGroupes('nope');
    }

    public function testComputedGroupNeedsRule(): void
    {
        $group = $this->computedGroup();
        $group->setMembresJson(null);

        $this->assertContains('membresJson', $this->violations($group));
        $this->assertSame([], $this->violations($this->computedGroup()));
    }

    public function testDeletingOwnerDeletesStoredRules(): void
    {
        $before = $this->storedRuleCount();
        $rule = $this->publicationRule();
        $rule->setSousGroupesRuleJson(self::json('attribution.profondeur', ['op' => 'le', 'value' => 1]));
        $group = $this->computedGroup();
        $this->em->persist($rule);
        $this->em->persist($group);
        $this->em->flush();
        $this->assertSame($before + 3, $this->storedRuleCount());

        $this->em->remove($rule);
        $this->em->remove($group);
        $this->em->flush();

        $this->assertSame($before, $this->storedRuleCount());
    }

    public function testLabelerNamesOwners(): void
    {
        $rule = $this->publicationRule();
        $group = $this->computedGroup();
        $this->em->persist($rule);
        $this->em->persist($group);
        $this->em->flush();
        $labeler = static::getContainer()->get(DirectoryRuleLabeler::class);

        $this->assertStringContainsString('Unités', $labeler->label($rule->getSelection()));
        $this->assertStringContainsString('chefs', $labeler->label($group->getMembres()));
        $this->assertNull($labeler->label(new StoredRule('membre', '{}')));
    }
}
