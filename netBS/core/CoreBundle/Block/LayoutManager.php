<?php

namespace NetBS\CoreBundle\Block;

use NetBS\CoreBundle\Event\PreRenderLayoutEvent;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LayoutManager
{
    /**
     * @var BlockManager
     */
    protected $blockManager;

    /**
     * @var EventDispatcher
     */
    protected $dispatcher;

    /**
     * @var LayoutInterface[]
     */
    protected $layouts    = [];

    public function __construct(BlockManager $manager, EventDispatcherInterface $dispatcher)
    {
        $this->blockManager = $manager;
        $this->dispatcher   = $dispatcher;
    }

    public function registerLayout(LayoutInterface $layout) {

        $this->layouts[]    = $layout;
    }

    /**
     * @param $name
     * @return LayoutInterface
     * @throws \Exception
     */
    public function getLayout($name) {

        foreach($this->layouts as $layout)
            if($layout->getName() === $name)
                return $layout;

        throw new \Exception("No layout found with name $name");
    }

    /**
     * @param $layout
     * @param $config
     * @param array $layoutConfig
     * @return string
     */
    public function render($layout, $config, $layoutConfig = []) {

        $layout     = $this->getLayout($layout);
        $resolver   = new OptionsResolver();

        $layout->configureOptions($resolver);
        $params     = $resolver->resolve($layoutConfig);

        $this->dispatcher->dispatch(new PreRenderLayoutEvent($layout, $config, $params), PreRenderLayoutEvent::NAME);
        $this->renderBlocks($config);

        return $layout->render($config, $params);
    }

    /**
     * @param $layout
     * @param $config
     * @param array $layoutConfig
     * @return Response
     */
    public function renderResponse($layout, $config, $layoutConfig = []) {

        return new Response($this->render($layout, $config, $layoutConfig));
    }

    /**
     * @return LayoutConfigurator
     */
    public static function configurator() {

        return new LayoutConfigurator();
    }

    protected function renderBlocks(LayoutConfigurator $configurator) {
        $this->renderColumn($configurator);
    }

    protected function renderColumn(Column $column) {

        if($column->hasBlock()) {

            $block      = $this->blockManager->getBlock($column->getBlock()->getBlockClass());
            $resolver   = new OptionsResolver();
            $block->configureOptions($resolver);
            $params     = $column->getBlock()->validate($resolver);
            $column->getBlock()->setContent($block->render($params));
        }

        else
            foreach($column->getRows() as $row)
                $this->renderRow($row);
    }

    protected function renderRow(Row $row) {

        foreach($row->getColumns() as $column)
            $this->renderColumn($column);
    }
}
