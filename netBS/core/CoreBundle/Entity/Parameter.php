<?php

namespace NetBS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Parameter
 *
 * @ORM\Table(name="netbs_core_parameters")
 * @ORM\Entity()
 */
class Parameter
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="namespace", type="string", length=255)
     */
    private $namespace;

    /**
     * @var string
     *
     * @ORM\Column(name="paramKey", type="string", length=255)
     */
    private $paramKey;

    /**
     * @var string
     *
     * @ORM\Column(name="value", type="string", length=255)
     */
    private $value;

    public function __construct($namespace, $key, $value)
    {
        $this->namespace    = $namespace;
        $this->paramKey     = $key;
        $this->value        = $value;
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->paramKey;
    }

    /**
     * @param string $key
     * @return $this
     */
    public function setKey($key)
    {
        $this->paramKey = $key;
        return $this;
    }

    /**
     * Set value
     *
     * @param string $value
     *
     * @return Parameter
     */
    public function setValue($value)
    {
        $this->value = $value;

        return $this;
    }

    /**
     * Get value
     *
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * @param string $namespace
     * @return $this
     */
    public function setNamespace($namespace)
    {
        $this->namespace = $namespace;
        return $this;
    }
}

