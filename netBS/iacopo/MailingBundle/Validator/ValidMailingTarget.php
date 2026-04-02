<?php

namespace Iacopo\MailingBundle\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
#[\Attribute]
class ValidMailingTarget extends Constraint
{
    public $message = 'Le champ {{ field }} est requis pour le type "{{ type }}".';
    public $circularReferenceMessage = 'Une liste ne peut pas se référencer elle-même.';

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
