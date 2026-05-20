<?php

declare(strict_types=1);

namespace NetBS\CoreBundle\Controller\Trait;

use Doctrine\DBAL\Exception\ConstraintViolationException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Form\FormResponseAttributes;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

trait HandlesFormPersistenceTrait
{
    /** @var array<class-string<ConstraintViolationException>, string> */
    private const CONSTRAINT_MESSAGES = [
        UniqueConstraintViolationException::class => 'Une de ces valeurs existe déjà dans la base. Vérifiez les champs qui doivent être uniques.',
        NotNullConstraintViolationException::class => "Un champ obligatoire n'a pas été renseigné.",
        ForeignKeyConstraintViolationException::class => "Une référence à une autre entité n'est plus valide.",
        ConstraintViolationException::class => 'Contrainte de base de données.',
    ];

    protected function flushOrAttachConstraintError(
        EntityManagerInterface $em,
        FormInterface $form,
        ?Request $request = null
    ): bool {
        try {
            $em->flush();
            return true;
        } catch (ConstraintViolationException $e) {
            $message = self::CONSTRAINT_MESSAGES[$e::class] ?? self::CONSTRAINT_MESSAGES[ConstraintViolationException::class];
            $form->addError(new FormError($message . ' (' . $e->getMessage() . ')'));
        }

        // Symfony's validator already ran and passed before flush() — its
        // POST_SUBMIT hook (InvalidFormStatusExtension) won't re-fire, so we
        // set the 422-trigger attribute by hand.
        if ($request !== null) {
            $request->attributes->set(FormResponseAttributes::ROOT_INVALID, true);
        }

        return false;
    }
}
