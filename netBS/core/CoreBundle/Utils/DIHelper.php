<?php

namespace NetBS\CoreBundle\Utils;

class DIHelper
{
    public static function getTraits(\ReflectionClass $objectClass) {

        $traitsNames    = $objectClass->getTraitNames();

        foreach($objectClass->getTraits() as $trait)
            $traitsNames[]  = $trait->getName();

        if($objectClass->getParentClass() != false)
            $traitsNames  = array_merge($traitsNames, self::getTraits($objectClass->getParentClass()));

        return $traitsNames;
    }

}