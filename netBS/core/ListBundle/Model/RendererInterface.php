<?php

namespace NetBS\ListBundle\Model;

interface RendererInterface
{
    /**
     * Returns this renderer's name
     * @return string
     */
    public function getName();

    /**
     * Renders the given prototype table
     * @param SnapshotTable $table
     * @return string
     */
    public function render(SnapshotTable $table, $params = []);
}