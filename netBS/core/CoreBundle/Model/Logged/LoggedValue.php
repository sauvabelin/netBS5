<?php

namespace NetBS\CoreBundle\Model\Logged;

class LoggedValue
{
    private $value;

    public function __construct($value)
    {
        $this->value    = $value;
    }

    public function __serialize(): array
    {
        return ['value' => $this->value];
    }

    public function __unserialize(array $data): void
    {
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