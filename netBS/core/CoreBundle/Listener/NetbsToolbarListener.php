<?php

namespace NetBS\CoreBundle\Listener;

use NetBS\CoreBundle\Entity\LoggedChange;
use NetBS\CoreBundle\Event\NetbsRendererToolbarEvent;
use NetBS\CoreBundle\ListModel\Renderer\BasicToolbarItem;
use NetBS\CoreBundle\Service\DynamicListManager;
use NetBS\CoreBundle\Service\ExporterManager;
use NetBS\CoreBundle\Service\ListBridgeManager;
use NetBS\CoreBundle\Service\MassUpdaterManager;
use NetBS\FichierBundle\Service\FichierConfig;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

class NetbsToolbarListener
{
    /**
     * @var DynamicListManager
     */
    private $dynamicManager;

    /**
     * @var ListBridgeManager
     */
    private $bridgeManager;

    /**
     * @var ExporterManager
     */
    private $exporterManager;

    /**
     * @var TokenStorage
     */
    private $storage;

    /**
     * @var MassUpdaterManager
     */
    private $massUpdaterManager;

    /**
     * @var Environment
     */
    private $twig;

    /**
     * @var FichierConfig
     */
    private $config;

    public function __construct(DynamicListManager $dynamicManager, ListBridgeManager $bridgeManager, ExporterManager $exporterManager, MassUpdaterManager $updater, Environment $twig, FichierConfig $config, TokenStorageInterface $storage)
    {
        $this->dynamicManager       = $dynamicManager;
        $this->bridgeManager        = $bridgeManager;
        $this->exporterManager      = $exporterManager;
        $this->massUpdaterManager   = $updater;
        $this->twig                 = $twig;
        $this->config               = $config;
        $this->storage              = $storage;
    }

    /**
     * @param NetbsRendererToolbarEvent $event
     */
    public function extend(NetbsRendererToolbarEvent $event) {

        $itemClass  = $event->getTable()->getItemClass();

        if($this->exporterManager->getExportersForClass($itemClass))
            $this->extendWithExporter($event);

        $this->extendWithMassUpdaters($event);

        $dynamics = false;
        if(in_array($itemClass, $this->dynamicManager->getManagedClasses()))
            $dynamics = true;

        foreach($this->dynamicManager->getManagedClasses() as $managedClass)
            if($this->bridgeManager->isValidTransformation($itemClass, $managedClass))
                $dynamics = true;
        if($dynamics)
            $this->extendWithDynamics($event);

        if($event->getTable()->getItemClass() === LoggedChange::class)
            $this->loggerFunctionnalities($event);
    }

    /**
     * @param NetbsRendererToolbarEvent $event
     */
    protected function extendWithExporter(NetbsRendererToolbarEvent $event) {

        $extensions = [];
        $itemClass  = $event->getTable()->getItemClass();

        foreach($this->exporterManager->getExportersForClass($itemClass) as $exporter) {

            $ext    = $exporter->getCategory();
            if(!isset($extensions[$ext]))
                $extensions[$ext] = [];

            $extensions[$ext][] = $exporter;
        }

        $content    = $this->twig->render('@NetBSCore/renderer/toolbar/export.button.twig', [
            'tableId'       => $event->getTableId(),
            'extensions'    => $extensions,
            'table'         => $event->getTable()
        ]);

        $event->getToolbar()->addItem(new BasicToolbarItem($content));
    }

    /**
     * @param NetbsRendererToolbarEvent $event
     */
    protected function extendWithMassUpdaters(NetbsRendererToolbarEvent $event) {

        $user       = $this->storage->getToken()->getUser();

        if(!$user || !$user->hasRole('ROLE_UPDATE_EVERYWHERE'))
            return;

        $addable    = $this->bridgeManager->isValidTransformation($event->getTable()->getItemClass(), $this->config->getMembreClass());
        $updatable  = $this->massUpdaterManager->getUpdaterForClass($event->getTable()->getItemClass());

        if(!$addable && !$updatable)
            return;

        $content    = $this->twig->render('@NetBSCore/renderer/toolbar/mass_update.button.twig', [
            'event'     => $event,
            'addable'   => $addable,
            'updatable' => $updatable
        ]);

        $event->getToolbar()->addItem(new BasicToolbarItem($content, 'right'));
    }

    /**
     * @param NetbsRendererToolbarEvent $event
     */
    protected function extendWithDynamics(NetbsRendererToolbarEvent $event) {

        $itemClass  = $event->getTable()->getItemClass();
        $lists      = $this->dynamicManager->getAvailableLists($itemClass);

        $content    = $this->twig->render('@NetBSCore/renderer/toolbar/dynamics.button.twig', [
            'event' => $event,
            'lists' => $lists
        ]);

        $event->getToolbar()->addItem(new BasicToolbarItem($content));
    }

    protected function loggerFunctionnalities(NetbsRendererToolbarEvent $event) {

        $content    = $this->twig->render('@NetBSCore/renderer/toolbar/logger.button.twig', [
            'event' => $event
        ]);

        $event->getToolbar()->addItem(new BasicToolbarItem($content));
    }
}
