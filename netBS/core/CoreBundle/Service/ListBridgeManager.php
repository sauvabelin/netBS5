<?php

namespace NetBS\CoreBundle\Service;

use Doctrine\Common\Util\ClassUtils;
use NetBS\CoreBundle\Model\Bridge\Dijkstra;
use NetBS\CoreBundle\Model\BridgeInterface;

class ListBridgeManager
{
    /**
     * @var BridgeInterface[]
     */
    protected $bridges  = [];

    /**
     * @var Dijkstra
     */
    protected $dijkstra;

    public function registerBridge(BridgeInterface $bridge) {

        $this->bridges[]    = $bridge;
    }

    protected function emptyRow() {

        $row    = [];
        foreach($this->bridges as $bridge) {
            $row[$bridge->getFromClass()] = 0;
            $row[$bridge->getToClass()] =0;
        }

        return $row;
    }

    public function buildGraph() {

        $graph  = [];

        foreach($this->bridges as $bridge) {

            if(!isset($graph[$bridge->getFromClass()]))
                $graph[$bridge->getFromClass()] = [];

            if(!isset($graph[$bridge->getToClass()]))
                $graph[$bridge->getToClass()] = [];

            $graph[$bridge->getFromClass()][$bridge->getToClass()] = $bridge->getCost();
        }

        $this->dijkstra = new Dijkstra($graph);
    }

    /**
     * @return BridgeInterface[]
     */
    public function getBridges()
    {
        return $this->bridges;
    }

    /**
     * @return array
     */
    public function getBridgesClasses() {

        $classes    = [];
        foreach($this->bridges as $bridge)
            $classes[] = $bridge->getFromClass();

        return $classes;
    }

    /**
     * Converts the given item and always returns the first result of the resultset
     * @param Object $item
     * @param string $destinationClass
     * @return Object
     */
    public function convertItem($item, $destinationClass) {

        $result = $this->convertItems([$item], $destinationClass);

        if(count($result) == 0) return null;
        return $result[0];
    }

    /**
     * Checks wether a bridge is available for the given required transformation
     * @param $from
     * @param $to
     * @return bool
     */
    public function isValidTransformation($from, $to) {

        return count($this->dijkstra->shortestPaths($from, $to)) > 0;
    }

    /**
     * @param \Traversable|\Countable $dataset
     * @param string $destinationClass
     * @return array
     */
    public function convertItems($dataset, $destinationClass) {

        if(count($dataset) == 0)
            return [];

        $fromClass = ClassUtils::getClass($dataset[0]);
        $pcc = $this->dijkstra->shortestPaths($fromClass, $destinationClass);

        if(count($pcc) == 0)
            return [];

        for($i = 0; $i < count($pcc[0]) - 1; $i++) {

            $transformer = null;
            foreach($this->bridges as $bridge)
                if($bridge->getFromClass() === $pcc[0][$i] && $bridge->getToClass() === $pcc[0][$i + 1])
                    $transformer = $bridge;

            $dataset = $transformer->transform($dataset);
        }

        return $dataset;
    }
}
