<?php

namespace NetBS\CoreBundle\Model;

interface LoaderInterface
{
    public function getLoadableClass();

    public function toId($item);

    public function fromId($id);
}
