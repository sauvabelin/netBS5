<?php

namespace NetBS\ListBundle\Model;

class SnapshotTable
{
    /**
     * @var ListModelInterface
     */
    protected $model;

    /**
     * @var ListColumnsConfiguration
     */
    protected $configuration;

    /**
     * @var array
     */
    protected $headers  = [];

    /**
     * @var array[]
     */
    protected $data     = [];

    /**
     * @var array
     */
    protected $items;

    public function __construct(ListModelInterface $model, $elements, ListColumnsConfiguration $configuration)
    {
        $this->model            = $model;
        $this->items            = $elements;
        $this->configuration    = $configuration;
    }

    /**
     * @return string
     */
    public function getItemClass() {

        $model  = $this->getModel();
        return $model->getManagedItemsClass();
    }

    /**
     * @return ListModelInterface
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * @return array
     */
    public function getItems()
    {
        return $this->items;
    }

    /**
     * @return ListColumnsConfiguration
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * @param $column
     * @param $title
     * @return $this
     */
    public function setHeader($column, $title) {

        $this->headers[$column] = $title;
        return $this;
    }

    /**
     * @return array
     */
    public function getHeaders() {

        return $this->headers;
    }

    /**
     * @param $row
     * @param $column
     * @param $data
     * @return $this
     */
    public function set($row, $column, $data) {

        $this->data[$row][$column] = $data;
        return $this;
    }

    /**
     * @param $row
     * @param $column
     * @return mixed
     */
    public function get($row, $column) {

        return $this->data[$row][$column];
    }

    public function getData() {

        return $this->data;
    }
}