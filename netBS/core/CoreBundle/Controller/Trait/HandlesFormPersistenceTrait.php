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

/**
 * Wraps `$em->flush()` to convert known Doctrine constraint violations into
 * form-level errors. The caller then re-renders the form with `$form->createView()`
 * and the user sees their input preserved + a French error message at the top.
 *
 * Use this in any controller that persists user-submitted data when the input
 * could realistically collide with a UNIQUE / NOT NULL / FK constraint. The
 * trait does not catch unrelated exceptions — those continue to surface
 * normally so genuine bugs are visible.
 *
 * Example:
 *
 *     if ($form->isSubmitted() && $form->isValid()) {
 *         $em->persist($entity);
 *         if ($this->flushOrAttachConstraintError($em, $form)) {
 *             return $this->redirectToRoute('…');
 *         }
 *         // fall through to re-render the form below
 *     }
 *
 *     return $this->render('…', ['form' => $form->createView()]);
 */
trait HandlesFormPersistenceTrait
{
    /**
     * Flushes the entity manager. On a known constraint violation, attaches
     * a French error message to the form and returns false. On any other
     * exception, lets it propagate.
     *
     * @return bool true on success (caller should redirect), false when an
     *              error was attached to the form (caller should re-render).
     */
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

        // Tell the InvalidFormStatusListener to bump the response to 422 — the
        // form now carries an error even though Symfony's validator already
        // ran (and passed) and won't re-fire its POST_SUBMIT hook.
        if ($request !== null) {
            $request->attributes->set(
                \App\Form\Extension\InvalidFormStatusExtension::REQUEST_ATTRIBUTE,
                true
            );
        }

        return false;
    }
}
