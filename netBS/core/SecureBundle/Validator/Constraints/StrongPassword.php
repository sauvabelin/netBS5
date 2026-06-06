<?php

namespace NetBS\SecureBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraints\Compound;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PasswordStrength;

/**
 * Single source of truth for the netBS password policy.
 *
 * Apply this to every place where a human enters a new password (reset flow,
 * my-account change, admin change, user creation) so the policy stays consistent
 * — changing it here changes all of them at once. Entropy-based (PasswordStrength)
 * rather than rigid char-class rules: it rejects weak/common passwords but accepts
 * long passphrases, and it runs fully offline (no breach-check network call).
 */
#[\Attribute]
class StrongPassword extends Compound
{
    protected function getConstraints(array $options): array
    {
        return [
            new NotBlank(message: 'Veuillez saisir un mot de passe.'),
            new Length(
                min: 10,
                minMessage: 'Le mot de passe doit comporter au moins {{ limit }} caractères.',
                max: 4096,
            ),
            new PasswordStrength(
                minScore: PasswordStrength::STRENGTH_MEDIUM,
                message: 'Ce mot de passe est trop faible. Choisissez-en un plus complexe (évitez les mots courants).',
            ),
        ];
    }
}
