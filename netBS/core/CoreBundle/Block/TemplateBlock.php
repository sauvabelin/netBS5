<?php

namespace NetBS\CoreBundle\Block;

use NetBS\CoreBundle\Utils\Traits\TwigTrait;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TemplateBlock implements BlockInterface
{
    use TwigTrait;

    /**
     * Configures all options required by this block to render itself
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setRequired('template')
            ->setDefault('params', []);
    }

    /**
     * Renders the block
     * @param array $params
     * @return string
     */
    public function render(array $params = [])
    {
        return $this->twig->render($params['template'], $params['params']);
    }
}