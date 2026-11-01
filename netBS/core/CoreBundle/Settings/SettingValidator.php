<?php

namespace NetBS\CoreBundle\Settings;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Rule\EntityTargets;
use NetBS\CoreBundle\Rule\RuleValidator;

/**
 * Checks a raw stored value against its declaration. Entity references are looked up with
 * the softdeleteable filter left on, so a row moved to the trash counts as deleted.
 */
class SettingValidator
{
    public function __construct(
        private EntityManagerInterface $em,
        private EntityTargets $targets,
        private RuleValidator $rules,
    ) {
    }

    /**
     * Validates several stored values at once, checking references with one query per target
     * class instead of one per setting (the admin banner runs this on every page).
     *
     * @param list<array{SettingDefinition,string}> $values
     * @return array<string,string> setting id => error message, for the invalid ones only
     */
    public function validateAll(array $values): array
    {
        $idsByClass = [];
        foreach ($values as [$d, $raw]) {
            if ($d->type === SettingType::Entity && preg_match('/^\d+$/', $raw)) {
                $idsByClass[$this->targetClass($d)][] = (int) $raw;
            }
        }

        $existing = [];
        foreach ($idsByClass as $class => $ids) {
            $found = $this->em->createQueryBuilder()
                ->select('e.id')
                ->from($class, 'e')
                ->where('e.id IN (:ids)')
                ->setParameter('ids', array_unique($ids))
                ->getQuery()
                ->getSingleColumnResult();
            $existing[$class] = array_flip(array_map('intval', $found));
        }

        $errors = [];
        foreach ($values as [$d, $raw]) {
            if ($d->type === SettingType::Entity && trim($raw) !== '') {
                $found = preg_match('/^\d+$/', $raw) && isset($existing[$this->targetClass($d)][(int) $raw]);
                $error = $found ? null : "Référence supprimée (id $raw).";
            } else {
                $error = $this->validate($d, $raw);
            }

            if ($error !== null) {
                $errors[$d->id()] = $error;
            }
        }

        return $errors;
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
            SettingType::Entity => $this->resolveEntity($d, $raw) === null ? "Référence supprimée (id $raw)." : null,
            SettingType::Rule   => null,
        };
    }

    public function resolveEntity(SettingDefinition $d, string $raw): ?object
    {
        if (!preg_match('/^\d+$/', $raw)) {
            return null;
        }

        // A query rather than find(): find() serves the identity map, which still holds a row
        // soft-deleted earlier in the same request.
        return $this->em->createQueryBuilder()
            ->select('e')
            ->from($this->targetClass($d), 'e')
            ->where('e.id = :id')
            ->setParameter('id', (int) $raw)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function targetClass(SettingDefinition $d): string
    {
        return $this->targets->classFor($d->target);
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
