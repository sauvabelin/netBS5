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
            if ($this->search) {
                $orTerms = [];
                foreach ($this->searchTerms() as $term) {
                    $orTerms[] = $queryBuilder->expr()->like("x." . $term, ":s");
                }
                $queryBuilder->andWhere(...$orTerms)
                    ->setParameter('s', '%' . $this->search . '%');
            }

            return $queryBuilder
                ->setMaxResults($this->amount)
                ->setFirstResult($this->page * $this->amount)
                ->getQuery()
                ->getResult();
        }

        return [];
    }

    public function retrieveAllIds() {
        $data = $this->ajaxQueryBuilder("x")
            ->getQuery()
            ->getResult();
        return array_map(fn ($item) => $item->getId(), $data);
    }

    abstract public function ajaxQueryBuilder(string $alias): QueryBuilder;

    abstract public function searchTerms(): array;
}