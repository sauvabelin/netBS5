<?php

namespace Iacopo\MailingBundle\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
class MailingDomain extends Constraint
{
    public $message = 'Le domaine de cette adresse n\'est pas géré par le serveur mail (domaines acceptés : {{ domains }}).';
}
