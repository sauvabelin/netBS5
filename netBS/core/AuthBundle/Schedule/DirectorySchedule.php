<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Schedule;

use NetBS\AuthBundle\Message\SyncDirectory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Periodic directory sync, run by `messenger:consume scheduler_directory`.
 * Also catches what no flush announces, e.g. attributions reaching their end date.
 */
#[AsSchedule('directory')]
final class DirectorySchedule implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())->add(RecurringMessage::every('15 minutes', new SyncDirectory()));
    }
}
