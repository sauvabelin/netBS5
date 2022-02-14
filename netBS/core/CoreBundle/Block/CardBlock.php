<?php

namespace NetBS\CoreBundle\Block;

use Symfony\Component\OptionsResolver\OptionsResolver;

class CardBlock extends BasicCardBlock
{
    public function configureOptions(OptionsResolver $resolver)
    {
        parent::configureOptions($resolver);

        $resolver->remove('body')
            ->setRequired('template')
            ->setDefault('params', []);
    }

    public function render(array $params = [])
    {
        $body   = $this->twig->render($params['template'], $params['params']);
        unset($params['template']);
        unset($params['params']);

        return parent::render(array_merge(['body' => $body], $params));
    }
}