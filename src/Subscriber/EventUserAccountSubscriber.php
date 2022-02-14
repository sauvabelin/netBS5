<?php

namespace App\Subscriber;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\SecureBundle\Event\UserPasswordChangeEvent;
use App\Entity\BSUser;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EventUserAccountSubscriber implements EventSubscriberInterface
{
    private $manager;

    public function __construct(EntityManagerInterface $manager)
    {
        $this->manager  = $manager;
    }

    public static function getSubscribedEvents()
    {
        return [
            UserPasswordChangeEvent::NAME => "passwordChanged"
        ];
    }

    /**
     * Appelé lorsque le mot de passe d'un utilisateur a changé depuis sa page de compte
     * @param UserPasswordChangeEvent $event
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     */
    public function passwordChanged(UserPasswordChangeEvent $event) {

        /** @var BSUser $user */
        $user   = $event->getUser();
        $user->setNewPasswordRequired(false);

        $lastCreatedAccount = $this->manager->getRepository('App:LatestCreatedAccount')
            ->findBy(array('user' => $user));

        if(is_array($lastCreatedAccount) && count($lastCreatedAccount) > 0)
            foreach($lastCreatedAccount as $item)
                $this->manager->remove($item);

        $this->manager->persist($user);
        $this->manager->flush();
    }
}
