<?php

namespace App\MessageHandler;

use App\Entity\BSGroupe;
use App\Entity\BSUser;
use App\Entity\TalkGroupMapping;
use App\Message\NextcloudGroupNotification;
use App\Service\NextcloudApiCall;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\FichierBundle\Mapping\BaseFonction;
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

    private $nc;

    public function __construct(
        NextcloudApiCall $nc,
        EntityManagerInterface $em,
        FichierConfig $fc,
        SecureConfig $sc)
    {
        $this->em = $em;
        $this->fichierConfig = $fc;
        $this->secureConfig = $sc;
        $this->nc = $nc;
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

            $this->nextcloudApiCall($user->getUsername(), $groupName, 'leave');
        }
    }

    private function joinGroup(BaseUser $user, string $groupName) {
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

            $this->nextcloudApiCall($user->getUsername(), $groupName, 'join');
        }
    }


    private static function groupeToNCID(BSGroupe $groupe) {
        return $groupe->getNcGroupName();
    }

    private static function fonctionToNCID(BaseFonction $fonction) {
        return "[" . $fonction->getId() . "] " . $fonction->getNom() . " (fonction)";
    }

    private function nextcloudApiCall(string $username, string $groupname, string $operation) {

        $usend = base64_encode($username);
        $gsend = base64_encode($groupname);
        $this->nc->query('POST', "/ocs/v2.php/apps/user_sql/api/sync", [
            'json' => [
                'username' => $usend,
                'groupname' => $gsend,
                'operation' => $operation,
            ],
        ]);
    }
}