<?php

namespace NetBS\CoreBundle\Block;

use NetBS\CoreBundle\Utils\Traits\TwigTrait;
use Symfony\Component\OptionsResolver\OptionsResolver;

class BasicCardBlock implements BlockInterface
{
    use TwigTrait;

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setRequired('title')
            ->setRequired('body')
            ->setDefault('subtitle', null)
            ->setDefault('borderColor', null)
            ->setDefault('backgroundColor', null)
            ->setDefault('divider', true)
            ->setDefault('table', false)
        ;
    }

    public function render(array $params = [])
    {
        return $this->twig->render('@NetBSCore/block/card.block.twig', $params);
    }
}