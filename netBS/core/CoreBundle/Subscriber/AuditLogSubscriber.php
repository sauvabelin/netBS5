<?php

namespace NetBS\CoreBundle\Subscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use NetBS\CoreBundle\Entity\AuditLog;
use NetBS\CoreBundle\Service\LoggerManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class AuditLogSubscriber implements EventSubscriber
{
    private TokenStorageInterface $storage;
    private LoggerManager $manager;
    private EntityManagerInterface $em;
    private ?LoggerInterface $logger;

    private array $pendingLogs = [];
    private bool $flushing = false;

    public function __construct(
        LoggerManager $manager,
        TokenStorageInterface $storage,
        EntityManagerInterface $em,
        ?LoggerInterface $logger = null
    ) {
        $this->storage = $storage;
        $this->manager = $manager;
        $this->em = $em;
        $this->logger = $logger;
    }

    public function getSubscribedEvents()
    {
        return [
            Events::postPersist,
            Events::preUpdate,
            Events::preRemove,
            Events::onFlush,
            Events::postFlush,
        ];
    }

    public function postPersist(LifecycleEventArgs $args)
    {
        $object = $args->getEntity();
        if (!$this->shouldLog($object)) return;

        $this->pendingLogs[] = $this->createAuditLogFor(AuditLog::ACTION_CREATE, $object);
    }

    public function preUpdate(PreUpdateEventArgs $args)
    {
        $object = $args->getEntity();
        if (!$this->shouldLog($object)) return;

        foreach ($args->getEntityChangeSet() as $property => $values) {
            if ($property === 'updatedAt') continue;

            $oldValue = $values[0];
            $newValue = $values[1];

            if ($property === 'deletedAt') {
                if ($oldValue === null && $newValue instanceof \DateTimeInterface) {
                    $this->pendingLogs[] = $this->createAuditLogFor(AuditLog::ACTION_DELETE, $object);
                } elseif ($oldValue instanceof \DateTimeInterface && $newValue === null) {
                    $this->pendingLogs[] = $this->createAuditLogFor(AuditLog::ACTION_RESTORE, $object);
                }
                continue;
            }

            $log = $this->createAuditLogFor(AuditLog::ACTION_UPDATE, $object);
            $log->setProperty($property);
            $log->setOldValue($this->manager->logValue($oldValue));
            $log->setNewValue($this->manager->logValue($newValue));
            $this->pendingLogs[] = $log;
        }
    }

    public function preRemove(LifecycleEventArgs $args)
    {
        $object = $args->getEntity();
        if (!$this->shouldLog($object)) return;

        $this->pendingLogs[] = $this->createAuditLogFor(AuditLog::ACTION_DELETE, $object);
    }

    /**
     * Capture audit logs for cascade soft-deleted/restored children.
     * CascadeSoftDeleteSubscriber runs in onFlush and modifies children via
     * recomputeSingleEntityChangeSet — those changes don't re-trigger preUpdate,
     * so we pick them up here by inspecting the UnitOfWork directly.
     */
    public function onFlush(OnFlushEventArgs $args)
    {
        $uow = $args->getObjectManager()->getUnitOfWork();

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$this->shouldLog($entity)) continue;

            $changeSet = $uow->getEntityChangeSet($entity);
            if (!isset($changeSet['deletedAt'])) continue;

            [$oldValue, $newValue] = $changeSet['deletedAt'];

            // Only log if we haven't already logged this entity in preUpdate
            $alreadyLogged = false;
            foreach ($this->pendingLogs as $log) {
                if ($log->getEntityClass() === ClassUtils::getClass($entity)
                    && $log->getEntityId() === $entity->getId()
                    && in_array($log->getAction(), [AuditLog::ACTION_DELETE, AuditLog::ACTION_RESTORE])) {
                    $alreadyLogged = true;
                    break;
                }
            }

            if ($alreadyLogged) continue;

            if ($oldValue === null && $newValue instanceof \DateTimeInterface) {
                $this->pendingLogs[] = $this->createAuditLogFor(AuditLog::ACTION_DELETE, $entity);
            } elseif ($oldValue instanceof \DateTimeInterface && $newValue === null) {
                $this->pendingLogs[] = $this->createAuditLogFor(AuditLog::ACTION_RESTORE, $entity);
            }
        }
    }

    public function postFlush()
    {
        if ($this->flushing || empty($this->pendingLogs)) {
            return;
        }

        $this->flushing = true;
        $logs = $this->pendingLogs;
        $this->pendingLogs = [];

        try {
            foreach ($logs as $log) {
                $this->em->persist($log);
            }
            $this->em->flush();
        } catch (\Throwable $e) {
            // Audit logging is secondary — don't let it break the user's operation
            if ($this->logger) {
                $this->logger->error('AuditLogSubscriber: Failed to persist audit logs', [
                    'exception' => $e,
                    'log_count' => count($logs),
                ]);
            }
        } finally {
            $this->flushing = false;
        }
    }

    protected function createAuditLogFor(string $action, object $object): AuditLog
    {
        $log   = new AuditLog();
        $class = ClassUtils::getClass($object);

        $token = $this->storage->getToken();
        if ($token && is_object($token->getUser())) {
            $log->setUser($token->getUser());
        }

        $log->setEntityClass($class);
        $log->setEntityId($object->getId());
        $log->setAction($action);

        // representBasic can throw if related entities are null/soft-deleted
        try {
            $rpzer = $this->manager->getLogRepresenter($class);
            $log->setDisplayName($rpzer->representBasic($object));
        } catch (\Throwable $e) {
            $log->setDisplayName($class . '#' . $object->getId());
        }

        return $log;
    }

    protected function shouldLog(object $object): bool
    {
        return $this->manager->canRepresent(ClassUtils::getClass($object));
    }
}
