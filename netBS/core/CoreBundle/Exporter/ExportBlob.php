<?php

namespace NetBS\CoreBundle\Exporter;

use Symfony\Component\HttpFoundation\Request;

class ExportBlob implements \Serializable
{
    /**
     * @var string
     */
    protected $key;

    /**
     * @var array
     */
    protected $ids;

    /**
     * @var string
     */
    protected $itemsClass;

    /**
     * @var string
     */
    protected $exporterAlias;

    /**
     * @var int
     */
    protected $configId;

    /**
     * ExportBlob constructor.
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $data                   = json_decode($request->get('data'), true);
        $this->key              = 'exporter_blob_' . uniqid() . time();
        $this->configId         = isset($data['configId']) ? $data['configId'] : null;
        $this->exporterAlias    = $data['exporterAlias'];
        $this->itemsClass       = base64_decode($data['itemsClass']);
        $this->ids              = $data['selectedIds'];
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @return array
     */
    public function getIds()
    {
        return $this->ids;
    }

    /**
     * @return string
     */
    public function getExporterAlias()
    {
        return $this->exporterAlias;
    }

    /**
     * @return string
     */
    public function getItemsClass()
    {
        return $this->itemsClass;
    }

    /**
     * @param int $configId
     */
    public function setConfigId($configId)
    {
        $this->configId = $configId;
    }

    /**
     * @return int
     */
    public function getConfigId()
    {
        return $this->configId;
    }

    public function serialize() {

        return serialize([
            'key'           => $this->key,
            'ids'           => $this->ids,
            'exporterAlias' => $this->exporterAlias,
            'itemsClass'    => $this->itemsClass,
            'configId'      => $this->configId
        ]);
    }

    public function unserialize($serialized)
    {
        $data                   = unserialize($serialized);
        $this->key              = $data['key'];
        $this->ids              = $data['ids'];
        $this->exporterAlias    = $data['exporterAlias'];
        $this->itemsClass       = $data['itemsClass'];
        $this->configId         = $data['configId'];
    }
}