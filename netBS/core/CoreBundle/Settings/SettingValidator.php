<?php

namespace NetBS\CoreBundle\Settings;

use NetBS\CoreBundle\Rule\RuleValidator;

/** Checks a raw value against its declaration, on every write path. */
class SettingValidator
{
    public function __construct(private RuleValidator $rules)
    {
    }

    /** @return string|null French error message, or null when valid */
    public function validate(SettingDefinition $d, ?string $raw): ?string
    {
        if ($d->type === SettingType::Rule) {
            return $this->rules->validate($raw, $d->context)[0] ?? null;
        }

        if ($raw === null || trim($raw) === '') {
            return 'Valeur requise.';
        }

        return match ($d->type) {
            SettingType::Text   => mb_strlen($raw) > 255 ? '255 caractères maximum.' : null,
            SettingType::Secret => $this->validateSecret($d, $raw),
            SettingType::Int    => $this->validateInt($d, $raw),
            SettingType::Choice => array_key_exists($raw, $d->choices) ? null : 'Valeur non autorisée.',
            SettingType::Rule   => null,
        };
    }

    private function validateSecret(SettingDefinition $d, string $raw): ?string
    {
        if (mb_strlen($raw) > 255) {
            return '255 caractères maximum.';
        }

        // Several passwords may be given separated by "|" (galerie.parent_token): each one must
        // stand on its own, an empty one would accept a request without any password.
        $passwords = explode('|', $raw);
        if (count($passwords) > 1) {
            foreach ($passwords as $password) {
                if (trim($password) === '' || ($d->minLength !== null && mb_strlen($password) < $d->minLength)) {
                    return $d->minLength !== null
                        ? "{$d->minLength} caractères minimum pour chaque mot de passe."
                        : 'Mot de passe vide.';
                }
            }

            return null;
        }

        return $d->minLength !== null && mb_strlen($raw) < $d->minLength ? "{$d->minLength} caractères minimum." : null;
    }

    private function validateInt(SettingDefinition $d, string $raw): ?string
    {
        if (!preg_match('/^-?\d+$/', $raw)) {
            return 'Nombre entier attendu.';
        }

        $value = (int) $raw;
        if (($d->min !== null && $value < $d->min) || ($d->max !== null && $value > $d->max)) {
            return match (true) {
                $d->min !== null && $d->max !== null => "Doit être entre {$d->min} et {$d->max}.",
                $d->min !== null                     => "Doit être au moins {$d->min}.",
                default                              => "Doit être au plus {$d->max}.",
            };
        }

        return null;
    }
}
