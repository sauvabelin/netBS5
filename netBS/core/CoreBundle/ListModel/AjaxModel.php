<?php

namespace NetBS\CoreBundle\ListModel;

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
        if ($this->page && $this->amount) {
            return $this->retrieveItems($this->page, $this->amount, $this->search);
        }

        return [];
    }

    abstract public function retrieveItems(int $page, int $amount, string | null $search);

    abstract public function retrieveAllIds();
}