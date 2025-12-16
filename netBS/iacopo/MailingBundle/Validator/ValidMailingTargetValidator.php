<?php

namespace Iacopo\MailingBundle\Validator;

use Iacopo\MailingBundle\Entity\MailingTarget;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class ValidMailingTargetValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint)
    {
        if (!$constraint instanceof ValidMailingTarget) {
            throw new UnexpectedTypeException($constraint, ValidMailingTarget::class);
        }

        if (!$value instanceof MailingTarget) {
            throw new UnexpectedTypeException($value, MailingTarget::class);
        }

        $type = $value->getType();

        // Map types to their required fields and field names for error messages
        $validations = [
            MailingTarget::TYPE_EMAIL => [
                'getter' => 'getTargetEmail',
                'field' => 'adresse email',
                'isEmpty' => function($val) { return empty($val); }
            ],
            MailingTarget::TYPE_USER => [
                'getter' => 'getTargetUser',
                'field' => 'utilisateur',
                'isEmpty' => function($val) { return $val === null; }
            ],
            MailingTarget::TYPE_UNITE => [
                'getter' => 'getTargetGroup',
                'field' => 'unité',
                'isEmpty' => function($val) { return $val === null; }
            ],
            MailingTarget::TYPE_ROLE => [
                'getter' => 'getTargetFonction',
                'field' => 'rôle',
                'isEmpty' => function($val) { return $val === null; }
            ],
            MailingTarget::TYPE_LIST => [
                'getter' => 'getTargetList',
                'field' => 'liste',
                'isEmpty' => function($val) { return $val === null; }
            ],
        ];

        if (!isset($validations[$type])) {
            return;
        }

        $validation = $validations[$type];
        $getter = $validation['getter'];
        $fieldValue = $value->$getter();

        // Check if the required field is empty
        if ($validation['isEmpty']($fieldValue)) {
            $this->context->buildViolation($constraint->message)
                ->setParameter('{{ field }}', $validation['field'])
                ->setParameter('{{ type }}', $type)
                ->addViolation();
            return;
        }

        // Special check for TYPE_LIST: prevent self-reference
        if ($type === MailingTarget::TYPE_LIST) {
            $targetList = $value->getTargetList();
            $mailingList = $value->getMailingList();

            if ($targetList && $mailingList && $targetList->getId() === $mailingList->getId()) {
                $this->context->buildViolation($constraint->circularReferenceMessage)
                    ->addViolation();
            }
        }
    }
}
