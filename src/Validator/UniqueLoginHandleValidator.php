<?php

namespace App\Validator;

use App\Entity\BSUser;
use App\Repository\BSUserRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

class UniqueLoginHandleValidator extends ConstraintValidator
{
    public function __construct(private readonly BSUserRepository $users)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueLoginHandle) {
            throw new UnexpectedTypeException($constraint, UniqueLoginHandle::class);
        }
        if ($value === null) {
            return;
        }
        if (!$value instanceof BSUser) {
            throw new UnexpectedValueException($value, BSUser::class);
        }

        if ($this->users->isHandleTaken([$value->getUsername(), $value->getLoginUsername()], $value->getId())) {
            $this->context->buildViolation($constraint->message)
                ->atPath('loginUsername')
                ->addViolation();
        }
    }
}
