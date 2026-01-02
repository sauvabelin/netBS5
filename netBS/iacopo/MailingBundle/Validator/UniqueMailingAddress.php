<?php

namespace Iacopo\MailingBundle\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 */
class UniqueMailingAddress extends Constraint
{
    public $message = 'Cette adresse est déjà utilisée dans le système.';

    public function getTargets()
    {
        return self::CLASS_CONSTRAINT;
    }
}
