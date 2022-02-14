<?php

namespace NetBS\ListBundle\Service;

use NetBS\ListBundle\Event\ListEvents;
use NetBS\ListBundle\Event\PostRenderListEvent;
use NetBS\ListBundle\Event\PreRenderListEvent;
use NetBS\ListBundle\Model\ConfiguredColumn;
use NetBS\ListBundle\Model\ListColumnsConfiguration;
use NetBS\ListBundle\Model\ListModelInterface;
use NetBS\ListBundle\Model\RendererInterface;
use NetBS\ListBundle\Model\SnapshotTable;
use NetBS\ListBundle\Utils\RenderedContent;
use NetBS\ListBundle\Utils\RenderedList;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class ListEngine
{
    /**
     * @var ListManager
     */
    protected $listModelManager;

    /**
     * @var RendererManager
     */
    protected $rendererManager;

    /**
     * @var ColumnManager
     */
    protected $columnManager;

    /**
     * @var Stopwatch
     */
    protected $stopwatch;

    /**
     * @var PropertyAccessorInterface
     */
    protected $accessor;

    /**
     * @var EventDispatcherInterface
     */
    protected $dispatcher;

    /**
     * @var RenderedList[]
     */
    protected $renderedLists = [];

    /**
     * @var ContainerInterface
     */
    protected $container;

    public function __construct(ListManager $listModelManager, RendererManager $rendererManager, ColumnManager $columnManager, PropertyAccessorInterface $accessor, EventDispatcherInterface $dispatcher, Stopwatch $stopwatch)
    {
        $this->listModelManager     = $listModelManager;
        $this->rendererManager      = $rendererManager;
        $this->columnManager        = $columnManager;
        $this->accessor             = $accessor;
        $this->stopwatch            = $stopwatch;
        $this->dispatcher           = $dispatcher;
    }

    public function getRenderedLists() {

        return $this->renderedLists;
    }

    /**
     * Renders a list
     * @param string|ListModelInterface $list
     * @param string|RendererInterface $renderer
     * @param array $params
     * @return RenderedContent
     * @throws \Exception
     */
    public function render($list, $renderer, $params = []) {

        if(is_string($list))
            $list       = $this->listModelManager->getModelByAlias($list);

        if(is_string($renderer))
            $renderer   = $this->rendererManager->getRendererByName($renderer);

        if(!$list instanceof ListModelInterface)
            throw new   \Exception("List model " . get_class($list) . " doesn't implement " . ListModelInterface::class);

        if(!$renderer instanceof RendererInterface)
            throw new \Exception("List renderer " . get_class($renderer) . " doesn't implement " . RendererInterface::class);

        foreach($params as $key => $value)
            $list->setParameter($key, $value);

        return $this->renderList($list, $renderer);
    }

    /**
     * @param ListModelInterface $list
     * @param RendererInterface $renderer
     * @return RenderedContent
     */
    public function renderList(ListModelInterface $list, RendererInterface $renderer) {

        $this->checkListModelConfiguration($list);

        $this->stopwatch->start($list->getAlias());

        $this->dispatcher->dispatch( new PreRenderListEvent($list, $renderer), ListEvents::PRE_RENDER);
        $snapshot = $this->generateSnaphot($list);
        $content = new RenderedContent($renderer->render($snapshot, $list->getRendererVariables()));

        $this->stopwatch->lap($list->getAlias());
        $this->dispatcher->dispatch(new PostRenderListEvent($list, $renderer, $content), ListEvents::POST_RENDER);

        $this->renderedLists[]  = new RenderedList($list, $renderer, $this->stopwatch->stop($list->getAlias()));

        return $content;
    }

    public function generateSnaphot(ListModelInterface $model) {

        $configuration  = new ListColumnsConfiguration();
        $items          = $model->getElements(true);
        $model->configureColumns($configuration);

        $snapshot = new SnapshotTable($model, $items, $configuration);
        $j        = 0;

        /** @var ConfiguredColumn $columnInfo */
        foreach($configuration->getColumns() as $columnInfo) {

            $column = $this->columnManager->getColumn($columnInfo->getClass());
            $snapshot->setHeader($j, $columnInfo->getHeader());

            $resolver   = new OptionsResolver();
            $column->configureOptions($resolver);
            $params     = $resolver->resolve($columnInfo->getParams());
            $columnInfo->setParams($params);

            $i = 0;
            foreach($items as $item)
                $snapshot->set($i++, $j, $column->getContent($this->getItemValue($item, $columnInfo->getAccessor()), $params));

            $j++;
        }

        return $snapshot;
    }

    protected function getItemValue($item, $accessor) {

        if($accessor == null)
            return $item;

        if($accessor instanceof \Closure)
            return $accessor($item);

        if(is_string($accessor)) {
            try {
                return $this->accessor->getValue($item, $accessor);
            } catch (\Exception $e) {
                return '';
            }
        }

        throw new \Exception("Invalid accessor");
    }

    protected function checkListModelConfiguration(ListModelInterface $listModel) {

        $resolver   = new OptionsResolver();
        $listModel->configureOptions($resolver);

        if(!empty($resolver->getDefinedOptions())) {

            $parameters = [];

            foreach($resolver->getDefinedOptions() as $option)
                $parameters[$option] = $listModel->getParameter($option);

            $parameters = $resolver->resolve($parameters);

            foreach($parameters as $key => $value)
                $listModel->setParameter($key, $value);
        }
    }
}
