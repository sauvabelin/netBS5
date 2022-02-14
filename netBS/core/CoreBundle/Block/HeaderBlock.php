<?php

namespace NetBS\CoreBundle\Block;

use NetBS\CoreBundle\Utils\Traits\TwigTrait;
use Symfony\Component\OptionsResolver\OptionsResolver;

class HeaderBlock implements BlockInterface
{
    use TwigTrait;

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired('header')
            ->setDefault('subHeader', null);
    }

    public function render(array $params = [])
    {
        return $this->twig->render('@NetBSCore/block/header.block.twig', $params);
    }
}