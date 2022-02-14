<?php

namespace NetBS\ListBundle\Model;

use Symfony\Component\OptionsResolver\OptionsResolver;

abstract class BaseListModel implements ListModelInterface
{
    protected $columnsConfiguration = null;

    protected $parameters           = [];

    protected $rendererVariables    = [];

    protected $managedItems         = null;

    /**
     * Retrieves all elements managed by this list
     * @return array
     */
    abstract protected function buildItemsList();

    public function getElements($refresh = false)
    {
        if(!$refresh && !is_null($this->managedItems))
            return $this->managedItems;

        $this->managedItems = $this->buildItemsList();
        return $this->managedItems;
    }

    /**
     * If not overridden, no parameters required
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
    }

    /**
     * Adds a custom variable provided to the renderer, if it can support any
     * @param $key
     * @param $value
     */
    public function addRendererVariable($key, $value) {
        $this->rendererVariables[$key] = $value;
    }

    /**
     * @return array
     */
    public function getRendererVariables()
    {
        return $this->rendererVariables;
    }

    /**
     * @return string
     */
    public function getContainedItemsName()
    {
        $data   = explode("\\", $this->getManagedItemsClass());
        $data   = end($data);

        return implode(" ", preg_split('/(?=[A-Z])/',$data));
    }

    /**
     * Sets a parameter required by the list to work
     * @param string $key the parameter key
     * @param mixed $value
     * @return $this
     */
    public function setParameter($key, $value)
    {
        $this->parameters[$key] = $value;
        return $this;
    }

    /**
     * Returns the parameter identified by the given key
     * @param string $key
     * @return mixed
     */
    public function getParameter($key)
    {
        if(!isset($this->parameters[$key]))
            return null;

        return $this->parameters[$key];
    }
}
