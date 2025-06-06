<?php

namespace App\Subscriber;

use App\Entity\BSUser;
use App\Message\NextcloudGroupNotification;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\LifecycleEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
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

    /**
     * @var array
     */
    private $messagesToSend = [];

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
            Events::postRemove,
            Events::postFlush
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
            $this->messagesToSend[] = new NextcloudGroupNotification(
                $user->getId(),
                $attribution->getGroupeId(),
                $attribution->getFonctionId(),
                'join');
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

            $changes[$property] = [
                'oldValue' => $oldValue,
                'newValue' => $newValue,
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

                $changes = $vals['changes'];
                $previousStart = $this->changeSetOldValue($changes, 'dateDebut', $attr->getDateDebut());
                $previousEnd = $this->changeSetOldValue($changes, 'dateFin', $attr->getDateFin());
                $previousFonction = $this->changeSetOldValue($changes, 'fonction', $attr->getFonction());
                $previousGroupe = $this->changeSetOldValue($changes, 'groupe', $attr->getGroupe());
                $previouslyActive = BaseAttribution::active($previousStart, $previousEnd);

                // Check if dates changed
                if (isset($changes['dateDebut']) || isset($changes['dateFin'])) {

                    // Previously active but no more, leave groups related to before
                    if ($previouslyActive && !$attr->isActive()) {
                        $this->messagesToSend[] = new NextcloudGroupNotification(
                            $this->getUser($attr, $args->getEntityManager())->getId(),
                            $previousGroupe->getId(),
                            $previousFonction->getId(),
                            'leave'
                        );
                        break;
                    }

                    // Previously not active but now yes, join groups related to now
                    if (!$previouslyActive && $attr->isActive()) {
                        $this->messagesToSend[] = new NextcloudGroupNotification(
                            $this->getUser($attr, $args->getEntityManager())->getId(),
                            $attr->getGroupeId(),
                            $attr->getFonctionId(),
                            'join'
                        );
                        break;
                    }

                    // Previously not active, now not active: Do nothing
                    // Previously active, now active: do changes regarding fonctions and groups
                }

                foreach($changes as $property => $change) {

                    $oldValue = $change['oldValue'];
                    $newValue = $change['newValue'];

                    if ($previouslyActive) {
                        // We might have to remove some groups
                        if ($property === 'groupe' && $oldValue->getId() !== $newValue->getId()) {
                            $this->messagesToSend[] = new NextcloudGroupNotification(
                                $this->getUser($attr, $args->getEntityManager())->getId(),
                                $oldValue->getId(),
                                null,
                                'leave'
                            );
                        }

                        // --- Fonction
                        if ($property === 'fonction' && $oldValue->getId() !== $newValue->getId()) {
                            $this->messagesToSend[] = new NextcloudGroupNotification(
                                $this->getUser($attr, $args->getEntityManager())->getId(),
                                null,
                                $oldValue->getId(),
                                'leave'
                            );
                        }
                    }

                    // If active now
                    if ($attr->isActive()) {
                        // --- Groupe
                        if ($property === 'groupe' && $oldValue->getId() !== $newValue->getId()) {
                            $this->messagesToSend[] = new NextcloudGroupNotification(
                                $this->getUser($attr, $args->getEntityManager())->getId(),
                                $newValue->getId(),
                                null,
                                'join'
                            );
                        }

                        // --- Fonction
                        if ($property === 'fonction' && $oldValue->getId() !== $newValue->getId()) {
                            $this->messagesToSend[] = new NextcloudGroupNotification(
                                $this->getUser($attr, $args->getEntityManager())->getId(),
                                null,
                                $newValue->getId(),
                                'join'
                            );
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
            $this->messagesToSend[] = new NextcloudGroupNotification(
                $this->getUser($attr, $args->getEntityManager())->getId(),
                $attr->getGroupeId(),
                $attr->getFonctionId(),
                'leave'
            );
        }
    }

    public function postFlush(PostFlushEventArgs $args) {
        // Send all waiting messages
        // We do it here so that changes on the DB are actually done, as nextcloud will check the db
        // before doing anything
        foreach ($this->messagesToSend as $message) {
            $this->bus->dispatch($message);
        }
    }

    private function changeSetOldValue($set, $prop, $default) {
        return isset($set[$prop]) ? $set[$prop]['oldValue'] : $default;
    }

    private function getUser(BaseAttribution $attribution, EntityManagerInterface $manager): BaseUser | null {
        $membre = $attribution->getMembre();
        return $manager->getRepository(BSUser::class)->findOneBy(array('membre' => $membre));
    }
}
