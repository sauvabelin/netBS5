<?php

namespace NetBS\CoreBundle\Model;

use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\Form;

abstract class BaseBinder implements BinderInterface
{
    public function bindType()
    {
        return self::BIND;
    }

    public function bind($alias, Form $form, QueryBuilder $builder)
    {
    }

    public function postFilter($item, $value, array $options)
    {
    }
}
