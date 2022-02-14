<?php

namespace NetBS\CoreBundle\Model;

use Doctrine\ORM\QueryBuilder;
use Symfony\Component\Form\Form;

interface BinderInterface
{
    const BIND = 'bind';
    const POST_FILTER = 'post';

    public function getType();

    public function bindType();

    public function bind($alias, Form $form, QueryBuilder $builder);

    public function postFilter($item, $value, array $options);
}
