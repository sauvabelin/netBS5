<?php

namespace NetBS\CoreBundle\Model;

use NetBS\CoreBundle\Entity\LoggedChange;

interface LogRepresenterInterface
{
    /**
     * This method will be called by the logger, its result will be database stored
     * alongside the change record
     * @param $item
     * @return string
     */
    public function representBasic($item);

    /**
     * This will be called by the logger when the user checking on changes asks for more
     * details regarding this object
     * @param object $item
     * @param string $action 'create','update', 'delete'
     * @param $property
     * @param $oldValue
     * @param $newValue
     * @return string
     */
    public function representDetails($item, $action, $property, $oldValue, $newValue);

    /**
     * The represented object class
     * @return string
     */
    public function getRepresentedClass();
}