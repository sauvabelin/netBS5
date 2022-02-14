<?php

namespace NetBS\CoreBundle\Select2;

use Doctrine\Common\Collections\Collection;

interface Select2ProviderInterface
{
    /**
     * Returns the class of the items managed by this provider
     * @return string
     */
    public function getManagedClass();

    /**
     * Returns string representation of the given managed object
     * @param $item
     * @return string
     */
    public function toString($item);

    /**
     * Returns the unique id for the item, used in the select2 field
     * @param $item
     * @return string
     */
    public function toId($item);

    /**
     * Search for objects related to the given needle
     * @param $needle
     * @param int $limit
     * @return Collection
     */
    public function search($needle, $limit = 5);
}