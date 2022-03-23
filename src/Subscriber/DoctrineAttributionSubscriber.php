<?php

namespace App\Subscriber;

use App\Message\NextcloudGroupNotification;
use Doctrine\Common\EventSubscriber;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use Doctrine\Persistence\ObjectManager;
use NetBS\CoreBundle\Entity\LoggedChange;
use NetBS\CoreBundle\Service\LoggerManager;
use NetBS\FichierBundle\Mapping\BaseAttribution;
use NetBS\SecureBundle\Mapping\BaseUser;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class DoctrineLoggerSubscriber implements EventSubscriber
{
    private $bus;

    /**
     * @var array
     */
    private $markedForUpdate  = [];

    /**
     * @var array
     */
    private $markedForRemoval = [];

    public function __construct(MessageBusInterface $bus)
    {
        $this->bus = $bus;
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

        if(!$args->getEntity() instanceof BaseAttribution)
            return;

        // New attribution, check if there's a user and if so notify nextcloud of it
        /** @var BaseAttribution $attribution */
        $attribution = $args->getEntity();
        $user = $this->getUser($attribution, $args->getEntityManager());

        if ($user) {
            $this->bus->dispatch(new NextcloudGroupNotification($user, $attribution->getGroupe(), 'join'));
        }
    }


    public function preUpdate(PreUpdateEventArgs $args) {

        if(!$args->getEntity() instanceof BaseAttribution)
            return;
        
        foreach($args->getEntityChangeSet() as $property => $values) {

            if($property === 'updatedAt')
                continue;

            $oldValue   = $values[0];
            $newValue   = $values[1];

            $this->markedForUpdate[] = [
                'property' => $property,
                'oldValue' => $oldValue,
                'newValue' => $newValue,
            ];
        }
    }

    public function postUpdate(LifecycleEventArgs $args) {

        foreach($this->markedForUpdate as $change) {
            $oldValue = $change['oldValue'];
            $newValue = $change['newValue'];
            $property = $change['property'];
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

    private function getUser(BaseAttribution $attribution, EntityManagerInterface $manager) {
        $membre = $attribution->getMembre();
        return $manager->getRepository('App:BSUser')->findOneBy(array('membre' => $membre));
    }
}
