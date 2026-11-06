<?php

declare(strict_types=1);

namespace App\Tests\CoreBundle\Rule;

use NetBS\CoreBundle\Rule\RuleRegistry;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class RuleRegistryTest extends KernelTestCase
{
    public function test_blocks_for_context(): void
    {
        self::bootKernel();
        $registry = self::getContainer()->get(RuleRegistry::class);

        $this->assertSame(['test.n', 'test.flag'], array_values(array_intersect(['test.n', 'test.flag'], array_keys($registry->blocksFor('test')))));
        $this->assertSame('test', $registry->context('test')->id());
        $this->assertNull($registry->block('nope'));

        $this->expectException(\LogicException::class);
        $registry->context('nope');
    }
}
