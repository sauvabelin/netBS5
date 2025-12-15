<?php

namespace Iacopo\MailingBundle\Service;

use App\Entity\BSUser;
use Doctrine\ORM\EntityManagerInterface;
use Iacopo\MailingBundle\Entity\MailingList;
use Iacopo\MailingBundle\Entity\MailingTarget;
use NetBS\FichierBundle\Entity\Attribution;

class MailingTargetResolver
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }
    /**
     * Resolve a single target to an array of email addresses
     *
     * @param MailingTarget $target
     * @return array Array of email addresses
     */
    public function resolveTarget(MailingTarget $target): array
    {
        $emails = [];

        switch ($target->getType()) {
            case MailingTarget::TYPE_EMAIL:
                if ($target->getTargetEmail()) {
                    $emails[] = $target->getTargetEmail();
                }
                break;

            case MailingTarget::TYPE_USER:
                $user = $target->getTargetUser();
                if ($user) {
                    // Use getEmail() directly - NEVER fall back to membre email
                    $email = $user->getEmail();
                    if ($email) {
                        $emails[] = $email;
                    }
                }
                break;

            case MailingTarget::TYPE_UNITE:
                $group = $target->getTargetGroup();
                if ($group) {
                    foreach ($group->getActivesAttributions() as $attribution) {
                        $membre = $attribution->getMembre();
                        if ($membre) {
                            // Get user from membre
                            $user = $this->getUserFromMembre($membre);
                            if ($user) {
                                // Use getEmail() directly - NEVER fall back to membre email
                                $email = $user->getEmail();
                                if ($email) {
                                    $emails[] = $email;
                                }
                            }
                        }
                    }
                }
                break;

            case MailingTarget::TYPE_ROLE:
                $fonction = $target->getTargetFonction();
                if ($fonction) {
                    // Get all active attributions with this fonction
                    $attributions = $this->entityManager
                        ->getRepository(Attribution::class)
                        ->createQueryBuilder('a')
                        ->where('a.fonction = :fonction')
                        ->setParameter('fonction', $fonction)
                        ->getQuery()
                        ->getResult();

                    foreach ($attributions as $attribution) {
                        if ($attribution->isActive()) {
                            $membre = $attribution->getMembre();
                            if ($membre) {
                                // Get user from membre
                                $user = $this->getUserFromMembre($membre);
                                if ($user) {
                                    // Use getEmail() directly - NEVER fall back to membre email
                                    $email = $user->getEmail();
                                    if ($email) {
                                        $emails[] = $email;
                                    }
                                }
                            }
                        }
                    }
                }
                break;

            case MailingTarget::TYPE_LIST:
                $nestedList = $target->getTargetList();
                if ($nestedList) {
                    // Recursively resolve the nested list
                    $emails = array_merge($emails, $this->resolveMailingList($nestedList));
                }
                break;
        }

        return array_unique($emails);
    }

    /**
     * Get the user associated with a membre
     *
     * @param $membre
     * @return BSUser|null
     */
    private function getUserFromMembre($membre): ?BSUser
    {
        return $this->entityManager
            ->getRepository(BSUser::class)
            ->createQueryBuilder('u')
            ->where('u.membre = :membre')
            ->setParameter('membre', $membre)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Resolve all targets in a mailing list to unique email addresses
     *
     * @param MailingList $mailingList
     * @return array Array of unique email addresses
     */
    public function resolveMailingList(MailingList $mailingList): array
    {
        $allEmails = [];

        foreach ($mailingList->getTargets() as $target) {
            $emails = $this->resolveTarget($target);
            $allEmails = array_merge($allEmails, $emails);
        }

        return array_values(array_unique($allEmails));
    }

    /**
     * Count unique email addresses for a single target
     *
     * @param MailingTarget $target
     * @return int
     */
    public function countTarget(MailingTarget $target): int
    {
        return count($this->resolveTarget($target));
    }

    /**
     * Count total unique email addresses in a mailing list
     *
     * @param MailingList $mailingList
     * @return int
     */
    public function countMailingList(MailingList $mailingList): int
    {
        return count($this->resolveMailingList($mailingList));
    }

    /**
     * Get detailed information about a target's resolved addresses
     *
     * @param MailingTarget $target
     * @return array Array with 'count' and 'emails' keys
     */
    public function getTargetDetails(MailingTarget $target): array
    {
        $emails = $this->resolveTarget($target);

        return [
            'count' => count($emails),
            'emails' => $emails,
            'display' => $target->getDisplayValue()
        ];
    }
}
