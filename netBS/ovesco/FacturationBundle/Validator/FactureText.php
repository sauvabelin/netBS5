<?php

namespace Ovesco\FacturationBundle\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * A new-format facture text: known {placeholders} only; [expression] valid, and only admins may
 * add or change one ($previous is the text before editing, already in the new format).
 */
#[\Attribute]
class FactureText extends Constraint
{
    public string $unknownMessage = 'Variable inconnue : {{ name }}.';
    public string $expertMessage  = 'Seul un administrateur peut ajouter ou modifier une expression [...].';
    public string $invalidMessage = 'Expression invalide : {{ error }}';

    public function __construct(public ?string $previous = null, mixed $options = null, ?array $groups = null, mixed $payload = null)
    {
        parent::__construct($options, $groups, $payload);
    }
}
