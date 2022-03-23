<?php

namespace App\MessageHandler;

use App\Entity\BSGroupe;
use App\Entity\BSUser;
use App\Message\NextcloudGroupNotification;
use Doctrine\ORM\EntityManagerInterface;
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

        /** @var BSGroupe $groupe */
        $groupe = $this->em->find($this->fichierConfig->getGroupeClass(), $message->getGroupeId());
        $operation = $message->getOperation();

        $membre = $user->getMembre();
        if ($operation === 'leave') {
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
            }
        } else if ($operation === 'join') {
            // TODO: Notify joined group
        }
    }
}