<?php

namespace Iacopo\MailingBundle\Validator;

use Iacopo\MailingBundle\Service\MailingDomains;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class MailingDomainValidator extends ConstraintValidator
{
    public function __construct(private readonly MailingDomains $domains)
    {
    }

    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof MailingDomain) {
            throw new UnexpectedTypeException($constraint, MailingDomain::class);
        }

        // Blank and malformed values are reported by NotBlank / Email.
        if (!is_string($value) || !str_contains($value, '@')) {
            return;
        }

        if (!$this->domains->allows(trim($value))) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ domains }}', implode(', ', $this->domains->all()))
                ->addViolation();
        }
    }
}
