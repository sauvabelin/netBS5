<?php

namespace NetBS\SecureBundle\EventSubscriber;

use NetBS\SecureBundle\Event\PasswordResetCompletedEvent;
use NetBS\SecureBundle\Event\PasswordResetRequestedEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PasswordResetLoggingSubscriber implements EventSubscriberInterface
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.security')]
        private readonly LoggerInterface $logger,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            PasswordResetRequestedEvent::class => 'onRequested',
            PasswordResetCompletedEvent::class => 'onCompleted',
        ];
    }

    public function onRequested(PasswordResetRequestedEvent $event): void
    {
        $this->logger->info(sprintf(
            'password_reset.requested user_id=%d username=%s ip=%s',
            $event->getUser()->getId(),
            $event->getUser()->getUsername(),
            $event->getIp() ?? '-',
        ));
    }

    public function onCompleted(PasswordResetCompletedEvent $event): void
    {
        $this->logger->info(sprintf(
            'password_reset.completed user_id=%d username=%s ip=%s',
            $event->getUser()->getId(),
            $event->getUser()->getUsername(),
            $event->getIp() ?? '-',
        ));
    }
}
