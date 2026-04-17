<?php

namespace NetBS\CoreBundle\Subscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use NetBS\CoreBundle\Entity\AuditLog;
use NetBS\CoreBundle\Service\LoggerManager;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class AuditLogSubscriber implements EventSubscriber
{
    private TokenStorageInterface $storage;
    private LoggerManager $manager;
    private EntityManagerInterface $em;

    private array $pendingLogs = [];
    private bool $flushing = false;

    public function __construct(LoggerManager $manager, TokenStorageInterface $storage, EntityManagerInterface $em)
    {
        $this->storage = $storage;
        $this->manager = $manager;
        $this->em = $em;
    }

    public function getSubscribedEvents()
    {
        return [
            Events::postPersist,
            Events::preUpdate,
            Events::preRemove,
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
        } finally {
            $this->flushing = false;
        }
    }

    protected function createAuditLogFor(string $action, object $object): AuditLog
    {
        $log   = new AuditLog();
        $class = ClassUtils::getClass($object);
        $rpzer = $this->manager->getLogRepresenter($class);

        $token = $this->storage->getToken();
        if ($token && is_object($token->getUser())) {
            $log->setUser($token->getUser());
        }

        $log->setEntityClass($class);
        $log->setEntityId($object->getId());
        $log->setAction($action);
        $log->setDisplayName($rpzer->representBasic($object));

        return $log;
    }

    protected function shouldLog(object $object): bool
    {
        return $this->manager->canRepresent(ClassUtils::getClass($object));
    }
}
