<?php

namespace NetBS\CoreBundle\Service;

use Doctrine\Common\Util\ClassUtils;
use NetBS\CoreBundle\Model\Helper\HelperInterface;

class HelperManager
{
    /**
     * @var HelperInterface[]
     */
    protected $helpers  = [];

    /**
     * @param HelperInterface $helper
     */
    public function pushHelper(HelperInterface $helper) {

        $this->helpers[$helper->getHelpableClass()] = $helper;
    }

    /**
     * @param $class
     * @return bool
     */
    public function existFor($class) {

        return $this->getFor($class) !== null;
    }

    /**
     * @param $class
     * @return HelperInterface|null
     * @throws \Exception
     */
    public function getFor($class) {

        if(is_object($class))
            $class  = ClassUtils::getClass($class);

        if(isset($this->helpers[$class]))
            return $this->helpers[$class];

        throw new \Exception("No helper found for $class");
    }
}