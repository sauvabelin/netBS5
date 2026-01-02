<?php

namespace Iacopo\MailingBundle\Validator;

use Doctrine\ORM\EntityManagerInterface;
use Iacopo\MailingBundle\Entity\MailingList;
use Iacopo\MailingBundle\Entity\MailingListAlias;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class UniqueMailingAddressValidator extends ConstraintValidator
{
    private $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof UniqueMailingAddress) {
            throw new UnexpectedTypeException($constraint, UniqueMailingAddress::class);
        }

        // Handle MailingListAlias - check if address is used anywhere else
        if ($value instanceof MailingListAlias) {
            $this->validateAlias($value, $constraint);
        }

        // Handle MailingList - check if base address is used anywhere else
        if ($value instanceof MailingList) {
            $this->validateBaseAddress($value, $constraint);
        }
    }

    private function validateAlias(MailingListAlias $alias, UniqueMailingAddress $constraint): void
    {
        $address = $alias->getAddress();
        if (!$address) {
            return;
        }

        $isUsed = false;

        // Check if this address is used as a base address anywhere
        $qb = $this->entityManager->createQueryBuilder();
        $existingList = $qb->select('ml')
            ->from(MailingList::class, 'ml')
            ->where('ml.baseAddress = :address')
            ->setParameter('address', $address)
            ->getQuery()
            ->getOneOrNullResult();

        if ($existingList) {
            $isUsed = true;
        }

        // Check if this address is used as another alias anywhere
        if (!$isUsed) {
            $qb = $this->entityManager->createQueryBuilder();
            $qb->select('a')
                ->from(MailingListAlias::class, 'a')
                ->where('a.address = :address')
                ->setParameter('address', $address);

            // Exclude the current alias if editing
            if ($alias->getId()) {
                $qb->andWhere('a.id != :currentId')
                    ->setParameter('currentId', $alias->getId());
            }

            $existingAlias = $qb->getQuery()->getOneOrNullResult();

            if ($existingAlias) {
                $isUsed = true;
            }
        }

        if ($isUsed) {
            $this->context->buildViolation($constraint->message)
                ->atPath('address')
                ->addViolation();
        }
    }

    private function validateBaseAddress(MailingList $list, UniqueMailingAddress $constraint): void
    {
        $baseAddress = $list->getBaseAddress();
        if (!$baseAddress) {
            return;
        }

        $isUsed = false;

        // Check if this base address is used as a base address in another list
        // (This is also checked by @UniqueEntity, but we include it for completeness)
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('ml')
            ->from(MailingList::class, 'ml')
            ->where('ml.baseAddress = :address')
            ->setParameter('address', $baseAddress);

        // Exclude the current list if editing
        if ($list->getId()) {
            $qb->andWhere('ml.id != :currentId')
                ->setParameter('currentId', $list->getId());
        }

        $existingList = $qb->getQuery()->getOneOrNullResult();

        if ($existingList) {
            $isUsed = true;
        }

        // Check if this base address is used as an alias anywhere
        if (!$isUsed) {
            $existingAlias = $this->entityManager->createQueryBuilder()
                ->select('a')
                ->from(MailingListAlias::class, 'a')
                ->where('a.address = :address')
                ->setParameter('address', $baseAddress)
                ->getQuery()
                ->getOneOrNullResult();

            if ($existingAlias) {
                $isUsed = true;
            }
        }

        if ($isUsed) {
            $this->context->buildViolation($constraint->message)
                ->atPath('baseAddress')
                ->addViolation();
        }
    }
}
