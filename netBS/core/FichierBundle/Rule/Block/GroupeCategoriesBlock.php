<?php

namespace NetBS\FichierBundle\Rule\Block;

use NetBS\CoreBundle\Rule\RuleBlock;
use NetBS\CoreBundle\Rule\RuleParam;
use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\FichierBundle\Rule\Ids;

class GroupeCategoriesBlock implements RuleBlock
{
    public function id(): string
    {
        return 'groupe.categories';
    }

    public function contexts(): array
    {
        return ['groupe'];
    }

    public function label(): string
    {
        return 'le type du groupe est de la catégorie';
    }

    public function negatedLabel(): ?string
    {
        return "le type du groupe n'est pas de la catégorie";
    }

    public function params(): array
    {
        return ['categories' => RuleParam::entities('groupe_categorie')];
    }

    public function describe(array $display, bool $not): string
    {
        return ($not ? "le type du groupe n'est pas de la catégorie " : 'le type du groupe est de la catégorie ') . $display['categories'];
    }

    /** @param BaseGroupe $subject */
    public function matches(object $subject, array $params): bool
    {
        return Ids::contains($params['categories'] ?? [], $subject->getGroupeType()?->getGroupeCategorie());
    }
}
