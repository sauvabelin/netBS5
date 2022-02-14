<?php

namespace NetBS\CoreBundle\Model;

class RouteHistory implements \Serializable
{
    /**
     * @var string
     */
    protected $routeName;

    /**
     * @var array|null
     */
    protected $params;

    /**
     * @var \DateTime
     */
    protected $date;

    public function __construct($routeName, $params)
    {
        $this->routeName    = $routeName;
        $this->params       = $params;
        $this->date         = new \DateTime();
    }

    /**
     * @return string
     */
    public function getRouteName()
    {
        return $this->routeName;
    }

    /**
     * @return array|null
     */
    public function getParams()
    {
        return $this->params;
    }

    public function serialize()
    {
        return serialize([
            'routeName' => $this->routeName,
            'params'    => json_encode($this->params),
            'date'      => $this->date
        ]);
    }

    public function unserialize($serialized)
    {
        $data   = unserialize($serialized);
        $this->routeName    = $data['routeName'];
        $this->params       = json_decode($data['params'], true);
        $this->date         = $data['date'];
    }
}