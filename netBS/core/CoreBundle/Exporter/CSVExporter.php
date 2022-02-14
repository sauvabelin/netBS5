<?php

namespace NetBS\CoreBundle\Exporter;

use NetBS\CoreBundle\Model\ExporterInterface;
use NetBS\CoreBundle\Utils\StrUtil;
use NetBS\CoreBundle\Utils\Traits\ParamTrait;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\PropertyAccess\PropertyAccessor;

abstract class CSVExporter implements ExporterInterface
{
    const   CATEGORY    = 'Excel/CSV';

    use ParamTrait;

    /**
     * @var PropertyAccessor
     */
    protected $accessor;

    public function setAccessor(PropertyAccessor $accessor)
    {
        $this->accessor = $accessor;
    }

    /**
     * Returns the exported type, like "excel", "pdf" or something..
     * @return string
     */
    public function getCategory()
    {
        return self::CATEGORY;
    }

    /**
     * @param CSVColumns $columns
     */
    abstract public function configureColumns(CSVColumns $columns);

    public function filterItems($items) {
        return $items;
    }

    /**
     * Returns an exported representation of given items
     * @param \Traversable $items
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function export($items)
    {
        $config = new CSVColumns();
        $this->configureColumns($config);

        $data   = [];
        $items  = $this->filterItems($items);
        $data[] = array_map(function($column) {
            return $column['header'];
        }, $config->getColumns());

        foreach($items as $item) {

            $row    = [];

            foreach($config->getColumns() as $column) {

                $accessor   = $column['accessor'];
                $value      = is_string($accessor) ? $this->accessor->getValue($item, $accessor) : $accessor($item);
                $row[]      = $value;
            }

            $data[] = $row;
        }

        $response = new StreamedResponse(function() use ($data) {
            foreach($data as $fields)
                echo implode(';', $fields) . "\n";
        });

        $response->headers->add([
            'Content-Type'          => 'application/octet-stream; charset=utf-8',
            'Pragma'                => 'public',
            'Cache-Control'         => 'maxage=1',
            'Content-Disposition'   => $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT, StrUtil::slugify($this->getName()) . time() . '.csv')
        ]);

        return $response;
    }
}
