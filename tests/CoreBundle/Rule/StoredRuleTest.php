<?php

declare(strict_types=1);

namespace App\Tests\CoreBundle\Rule;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Entity\StoredRule;
use NetBS\CoreBundle\Repository\StoredRuleRepository;
use NetBS\CoreBundle\Rule\Rule;
use NetBS\CoreBundle\Rule\RuleCondition;
use NetBS\CoreBundle\Rule\RuleGroup;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class StoredRuleTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->em->getConnection()->executeStatement("DELETE FROM netbs_core_rules WHERE context = 'test'");
    }

    protected function tearDown(): void
    {
        $this->em->getConnection()->executeStatement("DELETE FROM netbs_core_rules WHERE context = 'test'");
        parent::tearDown();
    }

    private function repository(): StoredRuleRepository
    {
        return $this->em->getRepository(StoredRule::class);
    }

    public function test_persist_and_reload(): void
    {
        $rule = Rule::blocks(new RuleGroup('and', [new RuleCondition('test.n', ['op' => 'ge', 'value' => 3])]));
        $row  = new StoredRule('test', $rule->toJson());
        $this->em->persist($row);
        $this->em->flush();
        $this->em->clear();

        $reloaded = $this->repository()->find($row->getId());
        $this->assertSame('test', $reloaded->getContext());
        $this->assertNull($reloaded->getCode());
        $this->assertEquals($rule, $reloaded->rule());
    }

    public function test_code_is_unique(): void
    {
        $this->em->persist(new StoredRule('test', Rule::empty()->toJson(), 'test.unique'));
        $this->em->persist(new StoredRule('test', Rule::empty()->toJson(), 'test.unique'));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->em->flush();
    }

    public function test_save_by_code_creates_then_updates(): void
    {
        $first = $this->repository()->saveByCode('test.code', 'test', Rule::expert('n > 1')->toJson());
        $this->em->flush();
        $second = $this->repository()->saveByCode('test.code', 'test', Rule::expert('n > 2')->toJson());
        $this->em->flush();
        $this->em->clear();

        $this->assertSame($first->getId(), $second->getId());
        $this->assertSame('n > 2', $this->repository()->findOneByCode('test.code')->rule()->expression);

        $this->repository()->deleteByCode('test.code');
        $this->em->flush();
        $this->assertNull($this->repository()->findOneByCode('test.code'));
    }

    public function test_copy_has_no_code(): void
    {
        $row  = new StoredRule('test', Rule::expert('n > 1')->toJson(), 'test.copy');
        $copy = $row->copy();

        $this->assertNull($copy->getCode());
        $this->assertNull($copy->getId());
        $this->assertSame('test', $copy->getContext());
        $this->assertSame($row->getDefinition(), $copy->getDefinition());
    }
}
