<?php

namespace Iacopo\MailingBundle\Select2;

use Doctrine\ORM\EntityManagerInterface;
use Iacopo\MailingBundle\Entity\MailingList;
use NetBS\CoreBundle\Select2\Select2ProviderInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class MailingListSelect2Provider implements Select2ProviderInterface
{
    private $entityManager;
    private $authorizationChecker;

    public function __construct(EntityManagerInterface $entityManager, AuthorizationCheckerInterface $authorizationChecker)
    {
        $this->entityManager = $entityManager;
        $this->authorizationChecker = $authorizationChecker;
    }

    public function getManagedClass(): string
    {
        return MailingList::class;
    }

    public function getClass(): string
    {
        return MailingList::class;
    }

    public function toString($entity): string
    {
        if (!$entity instanceof MailingList) {
            return '';
        }

        return $entity->getName() . ' (' . $entity->getBaseAddress() . ')';
    }

    public function toId($entity)
    {
        if (!$entity instanceof MailingList) {
            return null;
        }

        return $entity->getId();
    }

    public function search($needle, $limit = 5)
    {
        // The select2 endpoint is open to every logged-in user; list names and
        // addresses are for mailing managers only.
        if (!$this->authorizationChecker->isGranted('read', new MailingList())) {
            return [];
        }

        $qb = $this->entityManager
            ->getRepository(MailingList::class)
            ->createQueryBuilder('ml')
            ->orderBy('ml.name', 'ASC')
            ->setMaxResults($limit);

        if ($needle) {
            $qb->where('ml.name LIKE :query OR ml.baseAddress LIKE :query')
               ->setParameter('query', '%' . $needle . '%');
        }

        return $qb->getQuery()->getResult();
    }
}
