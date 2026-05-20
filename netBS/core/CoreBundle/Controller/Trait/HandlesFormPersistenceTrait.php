<?php

declare(strict_types=1);

namespace NetBS\CoreBundle\Controller\Trait;

use Doctrine\DBAL\Exception\ConstraintViolationException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

trait HandlesFormPersistenceTrait
{
    protected function flushOrAttachConstraintError(
        EntityManagerInterface $em,
        FormInterface $form,
        ?Request $request = null
    ): bool {
        try {
            $em->flush();
            return true;
        } catch (UniqueConstraintViolationException $e) {
            $form->addError(new FormError(
                'Une de ces valeurs existe déjà dans la base. ' .
                'Vérifiez les champs qui doivent être uniques. (' . $e->getMessage() . ')'
            ));
        } catch (NotNullConstraintViolationException $e) {
            $form->addError(new FormError(
                "Un champ obligatoire n'a pas été renseigné. (" . $e->getMessage() . ')'
            ));
        } catch (ForeignKeyConstraintViolationException $e) {
            $form->addError(new FormError(
                "Une référence à une autre entité n'est plus valide. (" . $e->getMessage() . ')'
            ));
        } catch (ConstraintViolationException $e) {
            $form->addError(new FormError(
                "Contrainte de base de données. (" . $e->getMessage() . ')'
            ));
        }

        // Symfony's validator already ran and passed before flush() — its
        // POST_SUBMIT hook (InvalidFormStatusExtension) won't re-fire, so we
        // set the 422-trigger attribute by hand.
        if ($request !== null) {
            $request->attributes->set(
                \App\Form\Extension\InvalidFormStatusExtension::REQUEST_ATTRIBUTE,
                true
            );
        }

        return false;
    }
}
