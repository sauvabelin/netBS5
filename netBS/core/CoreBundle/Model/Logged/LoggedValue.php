<?php

namespace NetBS\CoreBundle\Model\Logged;

class LoggedValue implements \Serializable
{
    private $value;

    public function __construct($value)
    {
        $this->value    = $value;
    }

    public function serialize()
    {
        return serialize(['value' => $this->value]);
    }

    public function unserialize($serialized)
    {
        $data           = unserialize($serialized);
        $this->value    = $data['value'];
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }
}