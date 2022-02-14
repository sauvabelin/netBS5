<?php

namespace Ovesco\FacturationBundle\Searcher;

use NetBS\CoreBundle\Model\BaseBinder;
use Ovesco\FacturationBundle\Form\Type\CompareType;
use Symfony\Component\PropertyAccess\PropertyAccess;

class CompareBinder extends BaseBinder
{
    private $propertyAccessor;

    public function __construct()
    {
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    public function bindType()
    {
        return self::POST_FILTER;
    }

    public function getType()
    {
        return CompareType::class;
    }

    public function postFilter($item, $value, array $options)
    {
        $fn = $options['function'];
        $data = $this->propertyAccessor->getValue($item, $options['property']);
        return $fn($data) === $fn($value);
    }
}
