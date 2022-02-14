<?php

namespace NetBS\CoreBundle\Block;

use NetBS\ListBundle\Service\ListEngine;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ListBlock extends BasicCardBlock
{
    protected $engine;

    public function __construct(ListEngine $engine)
    {
        $this->engine   = $engine;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->remove('body')
            ->setRequired('alias')
            ->setDefault('renderer', 'netbs')
            ->setDefault('params', []);
    }

    public function render(array $params = [])
    {
        $list = $this->engine->render($params['alias'], $params['renderer'], $params['params']);

        unset($params['alias']);
        unset($params['renderer']);
        unset($params['params']);

        $params['table']    = true;
        $params['body']     = $list;

        return parent::render($params);
    }
}