<?php

namespace App\MessageHandler;

use App\Entity\BSGroupe;
use App\Entity\BSUser;
use App\Message\NextcloudGroupNotification;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\FichierBundle\Mapping\BaseFonction;
use NetBS\FichierBundle\Service\FichierConfig;
use NetBS\SecureBundle\Service\SecureConfig;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class NextcloudGroupNotificationHandler
{
    private $em;

    private $fichierConfig;

    private $secureConfig;

    public function __construct(EntityManagerInterface $em, FichierConfig $fc, SecureConfig $sc)
    {
        $this->em = $em;
        $this->fichierConfig = $fc;
        $this->secureConfig = $sc;
    }

    public function __invoke(NextcloudGroupNotification $message)
    {
        /** @var BSUser $user */
        $user = $this->em->find($this->secureConfig->getUserClass(), $message->getUserId());

        /** @var BSGroupe|null $groupe */
        $groupe = $message->getGroupeId() ? $this->em->find($this->fichierConfig->getGroupeClass(), $message->getGroupeId()) : null;

        /** @var BaseFonction|null $fonction */
        $fonction = $message->getFonctionId() ? $this->em->find($this->fichierConfig->getFonctionClass(), $message->getFonctionId()) : null;

        $operation = $message->getOperation();

        $membre = $user->getMembre();

        if ($operation === 'leave') {
            if ($groupe) {
                // Check if user is really removed, I.E no other active subscription for this group
                $in = false;
                foreach ($membre->getActivesAttributions() as $attr) {
                    if ($attr->getGroupe() === $groupe) {
                        $in = true;
                        break;
                    }
                }

                if (!$in) {
                    // Member effectively no more in group, notify
                    // TODO: Notify no more in group
                    dump('leave', $user->getUsername(), $groupe->getNom());
                }
            }

            if ($fonction) {
                // Check if user is really removed, I.E no other active subscription for this group
                $in = false;
                foreach ($membre->getActivesAttributions() as $attr) {
                    if ($attr->getFonction() === $fonction) {
                        $in = true;
                        break;
                    }
                }

                if (!$in) {
                    // TODO: Notify no more in fonction
                    dump('leave', $user->getUsername(), $fonction->getNom());
                }
            }
        } else if ($operation === 'join') {
            if ($groupe) {
                // TODO: Notify add in groupe
                dump('join', $user->getUsername(), $groupe->getNom());
            }

            if ($fonction) {
                // TODO: Notify add in fonction
                dump('join', $user->getUsername(), $fonction->getNom());
            }
        }
    }
}