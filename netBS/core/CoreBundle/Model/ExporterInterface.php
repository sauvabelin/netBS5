<?php

namespace NetBS\CoreBundle\Model;

interface ExporterInterface
{
    /**
     * Returns an alias representing this exporter
     * @return string
     */
    public function getAlias();

    /**
     * Returns the exported item's class
     * @return string
     */
    public function getExportableClass();

    /**
     * Returns this exporter category, IE pdf, excel...
     * @return string
     */
    public function getCategory();

    /**
     * Returns a displayable name of this exporter
     * @return string
     */
    public function getName();

    /**
     * Returns a valid response to be returned directly
     * @param \Traversable $items
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function export($items);
}