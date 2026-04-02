<?php

namespace NetBS\CoreBundle\Model;

class RouteHistory
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

    public function __serialize(): array
    {
        return [
            'routeName' => $this->routeName,
            'params'    => json_encode($this->params),
            'date'      => $this->date
        ];
    }

    public function __unserialize(array $data): void
    {
        $this->routeName    = $data['routeName'];
        $this->params       = json_decode($data['params'], true);
        $this->date         = $data['date'];
    }
}