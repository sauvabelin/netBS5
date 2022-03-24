<?php

namespace App\MessageHandler;

use App\Entity\BSGroupe;
use App\Entity\BSUser;
use App\Entity\TalkGroupMapping;
use App\Message\NextcloudGroupNotification;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\FichierBundle\Mapping\BaseFonction;
use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\FichierBundle\Service\FichierConfig;
use NetBS\SecureBundle\Mapping\BaseUser;
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
                    $this->leaveGroup($user, self::groupeToNCID($groupe));
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
                    $this->leaveGroup($user, self::fonctionToNCID($fonction));
                }
            }
        } else if ($operation === 'join') {
            if ($groupe) {
                $this->joinGroup($user, self::groupeToNCID($groupe));
            }

            if ($fonction) {
                $this->joinGroup($user, self::fonctionToNCID($fonction));
            }
        }
    }

    private function leaveGroup(BaseUser $user, $groupName) {
        $mappings = $this->em->getRepository('App:TalkGroupMapping')->findBy([
            'username' => $user->getUsername(),
            'groupName' => $groupName,
        ]);

        if (count($mappings) > 0) {
            foreach ($mappings as $entry) {
                $this->em->remove($entry);
            }
            $this->em->flush();
        }
    }

    private function joinGroup(BaseUser $user, $groupName) {
        $mappings = $this->em->getRepository('App:TalkGroupMapping')->findBy([
            'username' => $user->getUsername(),
            'groupName' => $groupName,
        ]);

        if (count($mappings) === 0) {
            $entry = new TalkGroupMapping();
            $entry->setUsername($user->getUsername());
            $entry->setGroupName($groupName);
            $this->em->persist($entry);
            $this->em->flush();
        }
    }


    private static function groupeToNCID(BaseGroupe $groupe) {
        return BSGroupe::toNCGroupId($groupe);
    }

    private static function fonctionToNCID(BaseFonction $fonction) {
        return "[" . $fonction->getId() . "] " . $fonction->getNom() . " (fonction)";
    }
}