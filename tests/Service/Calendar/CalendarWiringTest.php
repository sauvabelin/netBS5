<?php

declare(strict_types=1);

namespace App\Tests\Service\Calendar;

use App\Service\Calendar\CalendarClientInterface;
use App\Service\Calendar\LocalCalendarClient;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CalendarWiringTest extends KernelTestCase
{
    public function test_non_prod_environments_never_talk_to_google(): void
    {
        self::bootKernel();

        $this->assertInstanceOf(LocalCalendarClient::class, self::getContainer()->get(CalendarClientInterface::class));
    }
}
