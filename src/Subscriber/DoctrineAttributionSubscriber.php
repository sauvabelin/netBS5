<?php

namespace App\Subscriber;

use App\Message\NextcloudGroupNotification;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use NetBS\FichierBundle\Mapping\BaseAttribution;
use NetBS\SecureBundle\Mapping\BaseUser;
use Symfony\Component\Messenger\MessageBusInterface;

class DoctrineAttributionSubscriber implements EventSubscriber
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
            $this->bus->dispatch(new NextcloudGroupNotification(
                $user->getId(),
                $attribution->getGroupeId(),
                $attribution->getFonctionId(),
                'join'));
        }
    }


    public function preUpdate(PreUpdateEventArgs $args) {

        /** @var BaseAttribution $attr */
        $attr = $args->getEntity();
        if(!$attr instanceof BaseAttribution)
            return;
        if (!$this->getUser($attr, $args->getEntityManager()))
            return;

        $changes = [];
        foreach($args->getEntityChangeSet() as $property => $values) {

            $oldValue   = $values[0];
            $newValue   = $values[1];

            $changes = [
                'property' => $property,
                'oldValue' => $oldValue,
                'newValue' => $newValue,
                'active'   => $attr->isActive(),
            ];
        }

        $this->markedForUpdate[] = [
            'attribution' => $attr,
            'changes' => $changes,
        ];
    }

    public function postUpdate(LifecycleEventArgs $args) {

        /** @var BaseAttribution $attr */
        $attr = $args->getEntity();

        // PostUpdate for a single attribution, find its previous values
        foreach ($this->markedForUpdate as $vals) {
            if ($vals['attribution']->getId() === $attr->getId()) {
                foreach($this->markedForUpdate as $change) {
                    $oldValue = $change['oldValue'];
                    $newValue = $change['newValue'];
                    $property = $change['property'];
                    $previouslyActive = $change['active'];

                    if ($previouslyActive) {
                        // We might have to remove some groups
                        if ($property === 'groupe' && $oldValue !== $newValue) {
                            if ($oldValue->isNcMapped()) {
                                $this->bus->dispatch(new NextcloudGroupNotification(
                                    $this->getUser($attr, $args->getEntityManager())->getId(),
                                    $oldValue->getId(),
                                    null,
                                    'leave'
                                ));
                            }
                        }

                        // --- Fonction
                        if ($property === 'fonction' && $oldValue !== $newValue) {
                            $this->bus->dispatch(new NextcloudGroupNotification(
                                $this->getUser($attr, $args->getEntityManager())->getId(),
                                null,
                                $oldValue->getId(),
                                'leave'
                            ));
                        }
                    }

                    // If active now
                    if ($attr->isActive()) {
                        // --- Groupe
                        if ($property === 'groupe' && $oldValue !== $newValue) {
                            if ($newValue->isNcMapped()) {
                                $this->bus->dispatch(new NextcloudGroupNotification(
                                    $this->getUser($attr, $args->getEntityManager())->getId(),
                                    $newValue->getId(),
                                    null,
                                    'join'
                                ));
                            }
                        }

                        // --- Fonction
                        if ($property === 'fonction' && $oldValue !== $newValue) {
                            $this->bus->dispatch(new NextcloudGroupNotification(
                                $this->getUser($attr, $args->getEntityManager())->getId(),
                                null,
                                $newValue->getId(),
                                'join'
                            ));
                        }
                    }
                }

            }
        }
    }

    public function preRemove(LifecycleEventArgs $args) {

        /** @var BaseAttribution $attr */
        $attr = $args->getEntity();
        if(!$attr instanceof BaseAttribution)
            return;
        if (!$this->getUser($attr, $args->getEntityManager()))
            return;

        $this->markedForRemoval[] = $attr;
    }

    public function postRemove(LifecycleEventArgs $args) {

        foreach($this->markedForRemoval as $attr) {
            $this->bus->dispatch(new NextcloudGroupNotification(
                $this->getUser($attr, $args->getEntityManager())->getId(),
                $attr->getGroupeId(),
                $attr->getFonctionId(),
                'leave'
            ));
        }
    }

    private function getUser(BaseAttribution $attribution, EntityManagerInterface $manager): BaseUser {
        $membre = $attribution->getMembre();
        return $manager->getRepository('App:BSUser')->findOneBy(array('membre' => $membre));
    }
}
