<?php

namespace NetBS\CoreBundle\Block;

class BlockManager
{
    private $blocks = [];

    public function registerBlock(BlockInterface $block) {

        $this->blocks[get_class($block)] = $block;
    }

    /**
     * @param $class
     * @return BlockInterface
     */
    public function getBlock($class) {

        return $this->blocks[$class];
    }
}