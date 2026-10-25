<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Listener;

use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use NetBS\AuthBundle\Message\SyncDirectory;
use NetBS\FichierBundle\Mapping\BaseAttribution;
use NetBS\FichierBundle\Mapping\BaseContactInformation;
use NetBS\FichierBundle\Mapping\BaseEmail;
use NetBS\FichierBundle\Mapping\BaseFamille;
use NetBS\FichierBundle\Mapping\BaseFonction;
use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\FichierBundle\Mapping\BaseGroupeType;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\SecureBundle\Mapping\BaseRole;
use NetBS\SecureBundle\Mapping\BaseUser;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

/**
 * Queues a directory sync after a flush that touches what the directory is
 * built from ({@see \NetBS\AuthBundle\Directory\DirectoryProjection}). The
 * delay lets a burst of edits end in one sync: the runner skips messages
 * requested before a sync started.
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class DirectoryChangeListener
{
    public const DELAY_MS = 30_000;

    private const WATCHED = [
        BaseUser::class, BaseRole::class,
        BaseMembre::class, BaseFamille::class, BaseContactInformation::class, BaseEmail::class,
        BaseAttribution::class, BaseFonction::class, BaseGroupe::class, BaseGroupeType::class,
    ];

    /** User fields the directory doesn't carry; changing only these doesn't queue a sync. */
    private const IGNORED_USER_FIELDS = ['password', 'salt', 'passwordChangedAt', 'newPasswordRequired'];

    private bool $changed = false;

    public function __construct(private readonly MessageBusInterface $bus)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();

        $entities = [...$uow->getScheduledEntityInsertions(), ...$uow->getScheduledEntityDeletions()];
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof BaseUser
                || array_diff(array_keys($uow->getEntityChangeSet($entity)), self::IGNORED_USER_FIELDS) !== []) {
                $entities[] = $entity;
            }
        }
        foreach ([...$uow->getScheduledCollectionUpdates(), ...$uow->getScheduledCollectionDeletions()] as $collection) {
            $entities[] = $collection->getOwner();
        }

        foreach ($entities as $entity) {
            foreach (self::WATCHED as $class) {
                if ($entity instanceof $class) {
                    $this->changed = true;
                    return;
                }
            }
        }
    }

    public function postFlush(): void
    {
        if (!$this->changed) {
            return;
        }
        $this->changed = false;
        $this->bus->dispatch(
            new SyncDirectory(new \DateTimeImmutable()),
            [new DelayStamp(self::DELAY_MS)],
        );
    }
}
