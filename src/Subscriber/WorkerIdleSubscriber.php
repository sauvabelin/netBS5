<?php

namespace App\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;

/**
 * Stops a worker once the queue is empty, for workers started by cron.
 * Disabled with MESSENGER_STOP_WHEN_IDLE=0 for a long-running worker, which
 * the scheduler needs (see NetBS\AuthBundle\Schedule\DirectorySchedule).
 */
class WorkerIdleSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly bool $stopWhenIdle)
    {
    }

    public function onWorkerRunning(WorkerRunningEvent $event): void
    {
        if ($this->stopWhenIdle && $event->isWorkerIdle()) {
            $event->getWorker()->stop();
        }
    }

    /**
     * @return array<string>
     */
    public static function getSubscribedEvents()
    {
        return [
            WorkerRunningEvent::class => 'onWorkerRunning',
        ];
    }
}
