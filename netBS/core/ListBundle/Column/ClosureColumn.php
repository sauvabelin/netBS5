<?php

namespace NetBS\ListBundle\Column;

use Symfony\Component\OptionsResolver\OptionsResolver;

class ClosureColumn extends BaseColumn
{
    const   CLOSURE = 'closure';

    public function getContent($item, array $params = [])
    {
        $closure    = $params[self::CLOSURE];
        return $closure($item);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver
            ->setRequired(self::CLOSURE);
    }
}
