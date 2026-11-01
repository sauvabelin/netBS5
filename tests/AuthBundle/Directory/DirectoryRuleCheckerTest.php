<?php

declare(strict_types=1);

namespace App\Tests\AuthBundle\Directory;

use NetBS\AuthBundle\Directory\DirectoryRuleChecker;
use NetBS\AuthBundle\Rule\DirectoryRuleLabeler;
use NetBS\CoreBundle\Entity\StoredRule;
use NetBS\CoreBundle\Rule\RuleEvaluator;
use NetBS\CoreBundle\Rule\RuleValidator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/** One checker serves one projection run, which may ask about the same rule for hundreds of groupes. */
final class DirectoryRuleCheckerTest extends KernelTestCase
{
    public function testReasonLabelsEachRuleOnce(): void
    {
        self::bootKernel();
        $labeler = $this->createMock(DirectoryRuleLabeler::class);
        $labeler->expects(self::once())->method('label')->willReturn('Règle LDAP « x »');
        $checker = new DirectoryRuleChecker(
            static::getContainer()->get(RuleValidator::class),
            static::getContainer()->get(RuleEvaluator::class),
            $labeler,
        );
        $rule = new StoredRule('groupe', '{nope');

        self::assertSame('Règle LDAP « x » : a', $checker->reason($rule, 'a'));
        self::assertSame('Règle LDAP « x » : a', $checker->reason($rule, 'a'));
        self::assertSame('Règle LDAP « x » : b', $checker->reason($rule, 'b'));
    }

    public function testMemoComputesOncePerKey(): void
    {
        self::bootKernel();
        $checker = new DirectoryRuleChecker(
            static::getContainer()->get(RuleValidator::class),
            static::getContainer()->get(RuleEvaluator::class),
            $this->createMock(DirectoryRuleLabeler::class),
        );
        $calls = 0;
        $compute = function () use (&$calls) { return ++$calls; };

        self::assertSame(1, $checker->memo('k', $compute));
        self::assertSame(1, $checker->memo('k', $compute));
        self::assertSame(2, $checker->memo('other', $compute));
    }
}
