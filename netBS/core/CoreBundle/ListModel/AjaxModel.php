<?php

namespace NetBS\CoreBundle\ListModel;

use Doctrine\ORM\QueryBuilder;
use NetBS\ListBundle\Model\BaseListModel;

abstract class AjaxModel extends BaseListModel
{
    protected ?int $page = null;
    protected ?int $amount = null;
    protected ?string $search = null;

    public function _setAjaxParams(int $page, int $amount, string | null $search) {
        $this->page = $page;
        $this->amount = $amount;
        $this->search = $search;
    }

    public function buildItemsList() {
        if ($this->page !== null && $this->amount !== null) {
            $queryBuilder = $this->ajaxQueryBuilder("x");
            $this->applySearchFilter($queryBuilder);

            return $queryBuilder
                ->setMaxResults($this->amount)
                ->setFirstResult($this->page * $this->amount)
                ->getQuery()
                ->getResult();
        }

        return [];
    }

    /**
     * Applies the active text search as an OR-across-searchTerms LIKE filter.
     * No-op when there's no search or no terms declared.
     */
    protected function applySearchFilter(QueryBuilder $queryBuilder): void {
        if (!$this->search) return;
        $terms = $this->searchTerms();
        if (count($terms) === 0) return;

        $orTerms = array_map(
            fn($t) => $queryBuilder->expr()->like("x.$t", ':s'),
            $terms,
        );
        $queryBuilder
            ->andWhere($queryBuilder->expr()->orX(...$orTerms))
            ->setParameter('s', '%' . $this->search . '%');
    }

    public function retrieveAllIds() {
        $data = $this->ajaxQueryBuilder("x")
            ->getQuery()
            ->getResult();
        return array_map(fn ($item) => $item->getId(), $data);
    }

    /**
     * Counts the total number of items matching the current search filter.
     * Used for server-side pagination.
     */
    public function countFilteredItems(): int {
        $queryBuilder = $this->ajaxQueryBuilder("x");
        $this->applySearchFilter($queryBuilder);

        return (int) $queryBuilder
            ->select('COUNT(x)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    abstract public function ajaxQueryBuilder(string $alias): QueryBuilder;

    abstract public function searchTerms(): array;
}