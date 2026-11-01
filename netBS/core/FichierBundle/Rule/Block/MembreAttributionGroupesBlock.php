<?php

namespace NetBS\FichierBundle\Rule\Block;

use NetBS\CoreBundle\Rule\RuleBlock;
use NetBS\CoreBundle\Rule\RuleParam;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\FichierBundle\Rule\Ids;

class MembreAttributionGroupesBlock implements RuleBlock
{
    private const SANS_SOUS_GROUPES = 'sans ses sous-groupes';

    public function id(): string
    {
        return 'membre.attribution_groupes';
    }

    public function contexts(): array
    {
        return ['membre'];
    }

    public function label(): string
    {
        return 'le membre a une attribution dans';
    }

    public function negatedLabel(): ?string
    {
        return "le membre n'a pas d'attribution dans";
    }

    public function params(): array
    {
        return [
            'groupes'      => RuleParam::entities('groupe'),
            'sous_groupes' => RuleParam::choice([
                'aucun'  => self::SANS_SOUS_GROUPES,
                'direct' => 'ou ses sous-groupes directs',
                'tous'   => 'ou tous ses sous-groupes',
            ]),
        ];
    }

    public function describe(array $display, bool $not): string
    {
        // « sans sous-groupes » is the plain case: the sentence leaves it out
        $suffix = $display['sous_groupes'] === self::SANS_SOUS_GROUPES ? '' : ' ' . $display['sous_groupes'];

        return ($not ? "le membre n'a pas d'attribution dans " : 'le membre a une attribution dans ') . $display['groupes'] . $suffix;
    }

    /** @param BaseMembre $subject */
    public function matches(object $subject, array $params): bool
    {
        $levels = match ($params['sous_groupes'] ?? 'aucun') {
            'direct' => 1,
            'tous'   => PHP_INT_MAX,
            default  => 0,
        };

        foreach ($subject->getActivesAttributions() as $attribution) {
            $groupe  = $attribution->getGroupe();
            $visited = [];
            for ($level = 0; $groupe !== null && $level <= $levels && !isset($visited[spl_object_id($groupe)]); $level++) {
                if (Ids::contains($params['groupes'] ?? [], $groupe)) {
                    return true;
                }
                $visited[spl_object_id($groupe)] = true;
                $groupe = $groupe->getParent();
            }
        }

        return false;
    }
}
