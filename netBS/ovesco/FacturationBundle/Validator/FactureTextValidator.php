<?php

namespace Ovesco\FacturationBundle\Validator;

use Ovesco\FacturationBundle\Rule\FactureContext;
use Ovesco\FacturationBundle\Service\FactureTextRenderer;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class FactureTextValidator extends ConstraintValidator
{
    public function __construct(
        private Security $security,
        private FactureContext $factureContext,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof FactureText) {
            throw new UnexpectedTypeException($constraint, FactureText::class);
        }
        if ($value === null || $value === '') {
            return;
        }

        preg_match_all(FactureTextRenderer::TOKEN, (string) $value, $matches, PREG_SET_ORDER);
        $language = new ExpressionLanguage();
        $expertDenied = false;

        foreach ($matches as $m) {
            if (!isset($m[3])) {
                if (!in_array($m[1], FactureTextRenderer::names(), true)) {
                    $this->context->buildViolation($constraint->unknownMessage)->setParameter('{{ name }}', '{' . $m[1] . '}')->addViolation();
                }
                continue;
            }

            if (!$expertDenied && !str_contains((string) $constraint->previous, $m[0]) && !$this->security->isGranted('ROLE_ADMIN')) {
                $this->context->buildViolation($constraint->expertMessage)->addViolation();
                $expertDenied = true;
            }

            try {
                $language->lint($m[3], array_keys($this->factureContext->variables()));
            } catch (SyntaxError $e) {
                $this->context->buildViolation($constraint->invalidMessage)->setParameter('{{ error }}', $e->getMessage())->addViolation();
            }
        }
    }
}
