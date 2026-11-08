<?php

declare(strict_types=1);

namespace App\Tests\ListBundle\Column;

use NetBS\ListBundle\Column\DateTimeColumn;
use PHPUnit\Framework\TestCase;
use Symfony\Component\OptionsResolver\OptionsResolver;

final class DateTimeColumnTest extends TestCase
{
    public function testFormatsWithTheFixedDateFormat(): void
    {
        $column   = new DateTimeColumn();
        $resolver = new OptionsResolver();
        $column->configureOptions($resolver);
        $params   = $resolver->resolve([]);

        $this->assertSame('31.12.2026', $column->getContent(new \DateTime('2026-12-31 18:30'), $params));
    }
}
