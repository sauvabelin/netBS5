<?php

namespace NetBS\CoreBundle\Model\Helper;

interface HelperInterface
{
    /**
     * Renders a helper view for the given item
     * @param $item
     * @return string
     */
    public function render($item);

    /**
     * Returns a route at which we can have more information regarding the given item.
     * For example if it's a membre, go to his main page
     * @param $item
     * @return string|null
     */
    public function getRoute($item);

    /**
     * Returns a string representation of the given item
     * @param $item
     * @return string
     */
    public function getRepresentation($item);

    /**
     * Returns the class of the helpable items in this helper
     * @return string
     */
    public function getHelpableClass();
}