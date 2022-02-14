<?php

namespace NetBS\CoreBundle\Model;

abstract class BaseMassUpdater
{
    /**
     * Override this to allow or disallow adding items
     * @return  bool
     */
    public function allowAdd() {

        return true;
    }

    public function getTitle() {

        return "Modifier la sélection";
    }

    /**
     * Override this to allow or disallow removing editing updated items
     * @return bool
     */
    public function allowDelete() {

        return true;
    }

    /**
     * Call item __toString() on each line to give intel
     * @return bool
     */
    public function showToString() {

        return false;
    }

    /**
     * Returns this updater name
     * @return string
     */
    abstract public function getName();

    /**
     * Returns the updated item class
     * @return string
     */
    abstract public function getUpdatedItemClass();

    /**
     * Returns type class used to render each item form
     * @return string
     */
    abstract public function getItemForm();
}