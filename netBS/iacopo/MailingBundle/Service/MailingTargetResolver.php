<?php

namespace Iacopo\MailingBundle\Service;

use App\Entity\BSUser;
use Doctrine\ORM\EntityManagerInterface;
use Iacopo\MailingBundle\Entity\MailingList;
use Iacopo\MailingBundle\Entity\MailingTarget;
use NetBS\FichierBundle\Entity\Attribution;
use Psr\Log\LoggerInterface;

class MailingTargetResolver
{
    private const MAX_NESTING_DEPTH = 10;

    private $entityManager;
    private $logger;

    public function __construct(EntityManagerInterface $entityManager, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    /**
     * Batch load users for a collection of membres (fixes N+1 query problem)
     *
     * @param array $membres Array of membre entities
     * @return array Associative array indexed by membre ID
     */
    private function getUsersForMembres(array $membres): array
    {
        if (empty($membres)) {
            return [];
        }

        $users = $this->entityManager
            ->getRepository(BSUser::class)
            ->createQueryBuilder('u')
            ->where('u.membre IN (:membres)')
            ->setParameter('membres', $membres)
            ->getQuery()
            ->getResult();

        // Index by membre ID for O(1) lookup
        $usersByMembreId = [];
        foreach ($users as $user) {
            if ($user->getMembre()) {
                $usersByMembreId[$user->getMembre()->getId()] = $user;
            }
        }
        return $usersByMembreId;
    }
    /**
     * Resolve a single target to an array of email addresses
     *
     * @param MailingTarget $target
     * @param array $visitedListIds Track visited lists to prevent circular references
     * @param int $depth Current nesting depth
     * @return array Array of email addresses
     */
    private function resolveTargetInternal(MailingTarget $target, array $visitedListIds = [], int $depth = 0): array
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
                    // Collect all membres first
                    $membres = [];
                    foreach ($group->getActivesAttributions() as $attribution) {
                        $membre = $attribution->getMembre();
                        if ($membre) {
                            $membres[] = $membre;
                        }
                    }

                    // Batch load users (fixes N+1 query problem)
                    $usersByMembreId = $this->getUsersForMembres($membres);

                    // Extract emails from loaded users
                    foreach ($membres as $membre) {
                        $user = $usersByMembreId[$membre->getId()] ?? null;
                        if ($user) {
                            $email = $user->getEmail();
                            if ($email) {
                                $emails[] = $email;
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

                    // Collect all membres from active attributions first
                    $membres = [];
                    foreach ($attributions as $attribution) {
                        if ($attribution->isActive()) {
                            $membre = $attribution->getMembre();
                            if ($membre) {
                                $membres[] = $membre;
                            }
                        }
                    }

                    // Batch load users (fixes N+1 query problem)
                    $usersByMembreId = $this->getUsersForMembres($membres);

                    // Extract emails from loaded users
                    foreach ($membres as $membre) {
                        $user = $usersByMembreId[$membre->getId()] ?? null;
                        if ($user) {
                            $email = $user->getEmail();
                            if ($email) {
                                $emails[] = $email;
                            }
                        }
                    }
                }
                break;

            case MailingTarget::TYPE_LIST:
                $nestedList = $target->getTargetList();
                if ($nestedList) {
                    $nestedListId = $nestedList->getId();
                    // Check for circular reference
                    if (!in_array($nestedListId, $visitedListIds)) {
                        // Recursively resolve the nested list with updated visited list and incremented depth
                        $emails = array_merge($emails, $this->resolveMailingListInternal($nestedList, $visitedListIds, $depth + 1));
                    }
                }
                break;
        }

        return array_unique($emails);
    }

    /**
     * Resolve a single target to an array of email addresses
     *
     * @param MailingTarget $target
     * @return array Array of email addresses
     */
    public function resolveTarget(MailingTarget $target): array
    {
        return $this->resolveTargetInternal($target, []);
    }

    /**
     * Resolve all targets in a mailing list to unique email addresses (internal with circular reference protection)
     *
     * @param MailingList $mailingList
     * @param array $visitedListIds Track visited lists to prevent circular references
     * @param int $depth Current nesting depth
     * @return array Array of unique email addresses
     */
    private function resolveMailingListInternal(MailingList $mailingList, array $visitedListIds = [], int $depth = 0): array
    {
        // Check max nesting depth to prevent excessive recursion
        if ($depth >= self::MAX_NESTING_DEPTH) {
            $this->logger->warning('Mailing list nesting depth exceeded', [
                'list_id' => $mailingList->getId(),
                'list_name' => $mailingList->getName(),
                'depth' => $depth
            ]);
            return [];
        }

        $allEmails = [];

        // Add current list to visited
        $visitedListIds[] = $mailingList->getId();

        foreach ($mailingList->getTargets() as $target) {
            $emails = $this->resolveTargetInternal($target, $visitedListIds, $depth);
            $allEmails = array_merge($allEmails, $emails);
        }

        return array_values(array_unique($allEmails));
    }

    /**
     * Resolve all targets in a mailing list to unique email addresses
     *
     * @param MailingList $mailingList
     * @return array Array of unique email addresses
     */
    public function resolveMailingList(MailingList $mailingList): array
    {
        return $this->resolveMailingListInternal($mailingList, []);
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
