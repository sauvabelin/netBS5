<?php

namespace NetBS\CoreBundle\Rule;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\FichierBundle\Service\FichierConfig;

/** Maps entity target names (groupe, fonction, …) to classes and loads live rows' labels. */
final class EntityTargets
{
    public function __construct(
        private EntityManagerInterface $em,
        private FichierConfig $fichierConfig,
    ) {
    }

    public function classFor(string $target): string
    {
        return match ($target) {
            'groupe'           => $this->fichierConfig->getGroupeClass(),
            'fonction'         => $this->fichierConfig->getFonctionClass(),
            'groupe_type'      => $this->fichierConfig->getGroupeTypeClass(),
            'groupe_categorie' => $this->fichierConfig->getGroupeCategorieClass(),
            default            => throw new \LogicException(sprintf('Unknown entity target "%s".', $target)),
        };
    }

    /** @return array<int,string> id => label, existing and not soft-deleted rows only */
    public function labels(string $target, array $ids): array
    {
        $class = $this->classFor($target);
        $ids   = array_values(array_unique(array_map('intval', array_filter($ids, fn ($id) => is_int($id) || ctype_digit((string) $id)))));
        if ($ids === []) {
            return [];
        }

        $rows = $this->em->createQueryBuilder()
            ->select('e')
            ->from($class, 'e')
            ->where('e.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $labels = [];
        foreach ($ids as $id) {
            foreach ($rows as $row) {
                if ($row->getId() === $id) {
                    $labels[$id] = (string) $row;
                }
            }
        }

        return $labels;
    }
}
