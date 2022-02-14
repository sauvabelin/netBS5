<?php

namespace NetBS\CoreBundle\Model;

interface EqualInterface
{
    /**
     * @param $object
     * @return bool
     */
    public function equals($object);
}