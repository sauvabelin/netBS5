<?php

declare(strict_types=1);

namespace App\Tests\CoreBundle\Form;

use NetBS\CoreBundle\Form\Type\DateMaskType;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;

final class DateMaskTypeTest extends KernelTestCase
{
    public function testShowsAndReadsTheFixedDateFormat(): void
    {
        $factory = static::getContainer()->get(FormFactoryInterface::class);

        // as in the real forms: a child field on an array-backed parent
        $form = $factory->createBuilder(FormType::class, ['date' => new \DateTime('2026-12-31')], ['csrf_protection' => false])
            ->add('date', DateMaskType::class)->getForm();
        $this->assertSame('31.12.2026', $form->createView()['date']->vars['value']);
        $this->assertStringContainsString("'mask' : '99.99.9999'", $form->get('date')->getConfig()->getOption('mask'));

        $form->submit(['date' => '01.02.2026']);
        $this->assertSame('2026-02-01', $form->get('date')->getData()->format('Y-m-d'));
    }
}
