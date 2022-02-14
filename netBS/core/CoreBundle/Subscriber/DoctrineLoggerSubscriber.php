<?php

namespace NetBS\CoreBundle\Subscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use NetBS\CoreBundle\Entity\LoggedChange;
use NetBS\CoreBundle\Service\LoggerManager;
use NetBS\SecureBundle\Mapping\BaseUser;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class DoctrineLoggerSubscriber implements EventSubscriber
{
    const   CREATE  = 'create';
    const   UPDATE  = 'update';
    const   DELETE  = 'delete';

    /**
     * @var TokenStorageInterface
     */
    private $storage;

    /**
     * @var LoggerManager
     */
    private $manager;

    /**
     * @var array
     */
    private $markedForUpdate  = [];

    /**
     * @var array
     */
    private $markedForRemoval = [];

    public function __construct(LoggerManager $manager, TokenStorageInterface $storage)
    {
        $this->storage  = $storage;
        $this->manager  = $manager;
    }

    public function getSubscribedEvents()
    {
        return [
            Events::postPersist,
            Events::preUpdate,
            Events::postUpdate,
            Events::preRemove,
            Events::postRemove
        ];
    }

    public function postPersist(LifecycleEventArgs $args) {

        if(!$this->shouldLog($args->getEntity()))
            return;

        $object     = $args->getEntity();
        $change     = $this->createLoggedChangeFor(self::CREATE, $object);
        $rpzer      = $this->manager->getLogRepresenter(ClassUtils::getClass($object));

        $change->setRepresentation($rpzer->representDetails($object, self::CREATE, null, null, null));
        $args->getEntityManager()->persist($change);
        $args->getEntityManager()->flush();
    }

    public function preUpdate(PreUpdateEventArgs $args) {

        if(!$this->shouldLog($args->getEntity()))
            return;

        $object = $args->getEntity();
        $rpzer  = $this->manager->getLogRepresenter(ClassUtils::getClass($object));

        foreach($args->getEntityChangeSet() as $property => $values) {

            if($property === 'updatedAt')
                continue;

            $change     = $this->createLoggedChangeFor(self::UPDATE, $args->getEntity());
            $oldValue   = $values[0];
            $newValue   = $values[1];

            $change->setProperty($property);
            $change->setOldValue($this->manager->logValue($oldValue));
            $change->setNewValue($this->manager->logValue($newValue));
            $change->setRepresentation($rpzer->representDetails($object, self::UPDATE, $property, $oldValue, $newValue));

            $this->markedForUpdate[] = $change;
        }
    }

    public function postUpdate(LifecycleEventArgs $args) {

        foreach($this->markedForUpdate as $change) {

            $args->getEntityManager()->persist($change);
            $args->getEntityManager()->flush();
        }
    }

    public function preRemove(LifecycleEventArgs $args) {

        if(!$this->shouldLog($args->getEntity()))
            return;

        $object     = $args->getEntity();
        $change     = $this->createLoggedChangeFor(self::DELETE, $object);
        $rpzer      = $this->manager->getLogRepresenter(ClassUtils::getClass($object));

        $change->setRepresentation($rpzer->representDetails($object, self::DELETE, null, null, null));
        $this->markedForRemoval[] = $change;
    }

    public function postRemove(LifecycleEventArgs $args) {

        foreach($this->markedForRemoval as $item) {

            $args->getEntityManager()->persist($item);
            $args->getEntityManager()->flush();
        }
    }

    /**
     * @param object $object
     * @return LoggedChange
     */
    protected function createLoggedChangeFor($action, $object) {

        $change     = new LoggedChange();
        $user       = $this->storage->getToken()->getUser();
        $id         = $object->getId();
        $class      = ClassUtils::getClass($object);
        $rpzer      = $this->manager->getLogRepresenter(ClassUtils::getClass($object));

        if($user) $change->setUser($user);
        $change->setObjectId($id)->setObjectClass($class)->setAction($action);
        $change->setDisplayName($rpzer->representBasic($object));

        return $change;
    }

    protected function shouldLog($object) {

        if(php_sapi_name() === 'cli') return false;

        if(!$this->manager->canRepresent(ClassUtils::getClass($object)))
            return false;

        /** @var BaseUser $user */

        $user   = $this->storage->getToken()->getUser();

        if($user->hasRole('ROLE_SG'))
            return false;

        return true;
    }
}
