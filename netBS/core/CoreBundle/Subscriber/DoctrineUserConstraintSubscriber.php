<?php

namespace NetBS\CoreBundle\Subscriber;

use Doctrine\ORM\Event\PreUpdateEventArgs;
use NetBS\CoreBundle\Exceptions\UserConstraintException;
use NetBS\CoreBundle\Validator\Constraints\UserValidator;

class DoctrineUserConstraintSubscriber
{
    private $validator;

    public function __construct(UserValidator $validator)
    {
        $this->validator    = $validator;
    }

    public function preUpdate(PreUpdateEventArgs $args): void {

        if(!$this->checkPermissions($args))
            throw new UserConstraintException("Vous n'êtes pas autorisé à modifier cette donnée");
    }

    private function checkPermissions(PreUpdateEventArgs $args) {

        $constraint = $this->validator->getConstraint($args->getObject());

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