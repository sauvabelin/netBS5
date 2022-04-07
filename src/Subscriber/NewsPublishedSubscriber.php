<?php

namespace App\Subscriber;

use App\Message\NewsNotification;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Events;
use NetBS\CoreBundle\Entity\News;
use Symfony\Component\Messenger\MessageBusInterface;

class NewsPublishedSubscriber implements EventSubscriber
{
    private $bus;

    public function __construct(MessageBusInterface $bus)
    {
        $this->bus = $bus;
    }

    public function getSubscribedEvents()
    {
        return [
            Events::postPersist,
        ];
    }

    public function postPersist(LifecycleEventArgs $args) {

        if(!$args->getEntity() instanceof News)
            return;

        $this->bus->dispatch(new NewsNotification($args->getEntity()->getId()));
    }
}
