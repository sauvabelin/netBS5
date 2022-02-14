<?php

namespace NetBS\CoreBundle\Model;

interface BridgeInterface
{
    /**
     * The given object class
     * @return string
     */
    public function getFromClass();

    /**
     * The outputed item class
     * @return string
     */
    public function getToClass();

    /**
     * Returns an estimation of the cost of the transformation (if multiple requests are needed...). Must be >= 0
     * @return int
     */
    public function getCost();

    /**
     * Converts $from an array of fromClass to an array of class toClass
     * @param object[] $from
     * @return object[]
     */
    public function transform($from);
}
