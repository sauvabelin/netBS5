<?php

namespace NetBS\CoreBundle\Model;

use NetBS\CoreBundle\Entity\DynamicList;

class AvailableList
{
    /**
     * @var DynamicList
     */
    private $list;

    /**
     * @var BridgeInterface[]
     */
    private $conversion;

    public function __construct(DynamicList $list, array $conversion = [])
    {
        $this->list = $list;
        $this->conversion = $conversion;
    }

    /**
     * @return DynamicList
     */
    public function getList()
    {
        return $this->list;
    }

    /**
     * @return BridgeInterface[]
     */
    public function isConversion()
    {
        return $this->conversion;
    }
}
