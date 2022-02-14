<?php

namespace NetBS\CoreBundle\Block;

use NetBS\CoreBundle\Block\Model\Tab;
use NetBS\CoreBundle\Utils\Traits\TwigTrait;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TabsCardBlock implements BlockInterface
{
    use TwigTrait;

    /**
     * Configures all options required by this block to render itself
     * @param OptionsResolver $resolver
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver
            ->setRequired('tabs')
            ->setDefault('table', false)
            ;
    }

    /**
     * Renders the block
     * @param array $params
     * @return string
     * @throws \Exception
     */
    public function render(array $params = [])
    {
        $tabs   = [];

        foreach($params['tabs'] as $tab) {

            if(!$tab instanceof Tab)
                throw new \Exception("Tabs must be instance of tab!");

            $tabs[] = [
                'title'     => $tab->getTitle(),
                'content'   => $this->twig->render($tab->getTemplate(), $tab->getParams())
            ];
        }

        return $this->twig->render('@NetBSCore/block/tabs.block.twig', [
            'tabs'  => $tabs,
            'table' => $params['table']
        ]);
    }
}