<?php

namespace NetBS\CoreBundle\ListModel;

use NetBS\ListBundle\Model\BaseListModel;

abstract class AjaxModel extends BaseListModel
{
    protected int $page;
    protected int $amount;
    protected string | null $search;

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

    abstract protected function retrieveItems(int $page, int $amount, string | null $search);

    abstract protected function retrieveAllIds();
}