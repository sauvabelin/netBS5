<?php

namespace NetBS\ListBundle\Service;

use NetBS\ListBundle\Exceptions\ListModelNotFoundException;
use NetBS\ListBundle\Model\ListModelInterface;

class ListManager
{
    /**
     * @var ListModelInterface[]
     */
    protected $registeredModels = [];

    public function registerListModel($id, ListModelInterface $registeredListModel) {

        $this->registeredModels[$id] = $registeredListModel;
    }

    public function getRegisteredModels() {

        return $this->registeredModels;
    }

    public function getModelById($id) {

        foreach($this->registeredModels as $id => $model)
            if($id == $id)
                return $model;

        throw new ListModelNotFoundException($id, 'id');
    }

    public function getModelByAlias($alias) {

        foreach($this->registeredModels as $model)
            if($model->getAlias() == $alias)
                return $model;

        throw new ListModelNotFoundException($alias, 'alias');
    }
}