<?php
namespace NetBS\CoreBundle\Model\Bridge;

class Dijkstra {

    /**
     * @var int[][]
     */
    protected $graph    = [];

    /**
     * @var int[]
     */
    protected $distance = [];

    /**
     * @var string[]
     */
    protected $previous = [];

    /**
     * @var int[]
     */
    protected $queue    = [];

    /**
     * @param integer[][] $graph
     */
    public function __construct($graph) {

        $this->graph = $graph;
    }

    /**
     * Process the next (i.e. closest) entry in the queue
     *
     * @param string[] $exclude A list of nodes to exclude - for calculating next-shortest paths.
     *
     * @return void
     */
    protected function processNextNodeInQueue(array $exclude) {

        $closest = array_search(min($this->queue), $this->queue);

        if (!empty($this->graph[$closest]) && !in_array($closest, $exclude)) {

            foreach ($this->graph[$closest] as $neighbor => $cost) {

                if (isset($this->distance[$neighbor])) {

                    if ($this->distance[$closest] + $cost < $this->distance[$neighbor]) {

                        $this->distance[$neighbor] = $this->distance[$closest] + $cost;
                        $this->previous[$neighbor] = array($closest);
                        $this->queue[$neighbor]    = $this->distance[$neighbor];
                    }

                    elseif ($this->distance[$closest] + $cost === $this->distance[$neighbor]) {

                        $this->previous[$neighbor][] = $closest;
                        $this->queue[$neighbor]      = $this->distance[$neighbor];
                    }
                }
            }
        }

        unset($this->queue[$closest]);
    }

    /**
     * Extract all the paths from $source to $target as arrays of nodes.
     *
     * @param string $target The starting node (working backwards)
     *
     * @return string[][] One or more shortest paths, each represented by a list of nodes
     */
    protected function extractPaths($target) {

        $paths = array(array($target));

        while (current($paths) !== false) {

            $key  = key($paths);
            $path = current($paths);

            next($paths);

            if (!empty($this->previous[$path[0]])) {

                foreach ($this->previous[$path[0]] as $previous) {

                    $copy = $path;
                    array_unshift($copy, $previous);
                    $paths[] = $copy;
                }

                unset($paths[$key]);
            }
        }

        return array_values($paths);
    }

    /**
     * Calculate the shortest path through a a graph, from $source to $target.
     *
     * @param string   $source  The starting node
     * @param string   $target  The ending node
     * @param string[] $exclude A list of nodes to exclude - for calculating next-shortest paths.
     *
     * @return string[][] Zero or more shortest paths, each represented by a list of nodes
     */
    public function shortestPaths($source, $target, array $exclude = array()) {

        $this->distance             = array_fill_keys(array_keys($this->graph), INF);
        $this->distance[$source]    = 0;
        $this->previous             = array_fill_keys(array_keys($this->graph), array());
        $this->queue                = array($source => 0);

        while (!empty($this->queue))
            $this->processNextNodeInQueue($exclude);

        if ($source === $target)
            return array(array($source));

        elseif (empty($this->previous[$target]))
            return array();

        else
            return $this->extractPaths($target);
    }
}
