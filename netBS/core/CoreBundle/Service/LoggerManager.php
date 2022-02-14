<?php

namespace NetBS\CoreBundle\Service;

use Doctrine\ORM\EntityManager;
use NetBS\CoreBundle\Model\Logged\LoggedEntity;
use NetBS\CoreBundle\Model\Logged\LoggedValue;
use NetBS\CoreBundle\Model\LogRepresenterInterface;

class LoggerManager
{
    /**
     * @var EntityManager
     */
    private $manager;

    public function setEntityManager(EntityManager $manager)
    {
        $this->manager  = $manager;
    }

    /**
     * @var LogRepresenterInterface[]
     */
    protected $representers = [];

    public function registerRepresenter(LogRepresenterInterface $logRepresenter) {

        $this->representers[$logRepresenter->getRepresentedClass()] = $logRepresenter;
    }

    /**
     * @param $class
     * @return bool
     */
    public function canRepresent($class) {

        return isset($this->representers[$class]);
    }

    /**
     * @param $class
     * @return LogRepresenterInterface
     */
    public function getLogRepresenter($class) {

        return $this->representers[$class];
    }

    public function logValue($value) {

        $result = null;
        if(LoggedEntity::valid($value))
            $result = new LoggedEntity($value);

        else $result = new LoggedValue($value);

        return serialize($result);
    }

    public function delogValue($dbval) {

        $dbval  = unserialize($dbval);

        if($dbval instanceof LoggedEntity)
            $this->manager->find($dbval->getClass(), $dbval->getId());

        if($dbval instanceof LoggedValue)
            return $dbval->getValue();

        return null;
    }
}
