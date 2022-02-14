<?php

namespace NetBS\CoreBundle\Model\Logged;

use Doctrine\Common\Util\ClassUtils;

class LoggedEntity implements \Serializable
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $class;

    public function __construct($object)
    {
        $this->id       = $object->getId();
        $this->class    = ClassUtils::getClass($object);
    }

    public function serialize()
    {
        return serialize([
            'id'    => $this->id,
            'class' => $this->class
        ]);
    }

    public function unserialize($serialized)
    {
        $data           = unserialize($serialized);
        $this->id       = $data['id'];
        $this->class    = $data['class'];
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * @param $object
     * @return bool
     */
    public static function valid($object) {

        return is_object($object) && method_exists($object, 'getId');
    }
}