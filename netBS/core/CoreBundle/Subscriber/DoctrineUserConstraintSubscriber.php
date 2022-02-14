<?php

namespace NetBS\CoreBundle\Subscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Events;
use NetBS\CoreBundle\Exceptions\UserConstraintException;
use NetBS\CoreBundle\Validator\Constraints\UserValidator;

class DoctrineUserConstraintSubscriber implements EventSubscriber
{
    private $validator;

    public function __construct(UserValidator $validator)
    {
        $this->validator    = $validator;
    }

    /**
     * Returns an array of events this subscriber wants to listen to.
     *
     * @return string[]
     */
    public function getSubscribedEvents()
    {
        return [
            Events::preUpdate
        ];
    }

    public function preUpdate(PreUpdateEventArgs $args) {

        if(!$this->checkPermissions($args))
            throw new UserConstraintException("Vous n'êtes pas autorisé à modifier cette donnée");
    }

    private function checkPermissions(PreUpdateEventArgs $args) {

        $constraint = $this->validator->getConstraint($args->getEntity());

        if(!$constraint)
            return true;

        if($constraint->rule)
            return $this->validator->validateRule($constraint->rule);

        foreach($args->getEntityChangeSet() as $property => $values) {
            if(isset($constraint->rules[$property]))
                if(!$this->validator->validateRule($constraint->rules[$property]))
                    return false;
        }

        return true;
    }
}