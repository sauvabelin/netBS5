<?php

namespace NetBS\CoreBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\CoreBundle\Entity\ExportConfiguration;
use NetBS\CoreBundle\Exporter\ExportBlob;
use NetBS\CoreBundle\Model\ConfigurableExporterInterface;
use NetBS\CoreBundle\Model\ExporterConfigInterface;
use NetBS\CoreBundle\Model\ExporterInterface;
use NetBS\CoreBundle\Service\ExporterManager;
use NetBS\CoreBundle\Service\ListBridgeManager;
use NetBS\CoreBundle\Service\LoaderManager;
use NetBS\CoreBundle\Service\PreviewerManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class ExportController
 * @Route("/export")
 */
class ExportController extends AbstractController
{
    /**
     * @Route("/export/selected", name="netbs.core.export.export_selected")
     * @param Request $request
     * @return Response
     */
    public function generateExportBlobAction(Request $request, ExporterManager $manager, EntityManagerInterface $em, LoaderManager $loaderManager, ListBridgeManager $listBridgeManager) {

        $session    = $this->get('session');
        $blob       = new ExportBlob($request);
        $exporter   = $manager->getExporterByAlias($blob->getExporterAlias());

        if(!$exporter instanceof ConfigurableExporterInterface) {

            $session->set($blob->getKey(), serialize($blob));
            return $this->generateExportAction($blob->getKey(), $manager, $em, $loaderManager, $listBridgeManager);
        }

        else {

            $configs = $this->getUserConfigurations($exporter, $em);
            $blob->setConfigId($configs[0]->getId());
            $session->set($blob->getKey(), serialize($blob));
        }

        return $this->redirectToRoute('netbs.core.export.check_settings', array('blobKey' => $blob->getKey()));
    }

    /**
     * @Route("/switch-config/{blobKey}/{configId}", name="netbs.core.export.switch_config")
     * @param $blobKey
     * @param $configId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function switchConfigAction($blobKey, $configId, ExporterManager $manager, EntityManagerInterface $em) {

        /** @var ExportBlob $blob */
        $blob       = unserialize($this->get('session')->get($blobKey));
        /** @var ConfigurableExporterInterface $exporter */
        $exporter   = $manager->getExporterByAlias($blob->getExporterAlias());
        $data       = explode("__", $configId);
        $config     = null;

        if($configId === 'new')
            $config = $this->getNewConfig($exporter->getBasicConfig(), $exporter->getAlias(), $em);

        elseif($data[0] === "model") {
            $base = array_filter($exporter->getBasicConfig(), function($config) use ($data) {
                return get_class($config) === base64_decode($data[1]);
            });

            $config = $this->getNewConfig(array_shift($base), $exporter->getAlias(), $em);
        }

        else
            $config = $em->getRepository('NetBSCoreBundle:ExportConfiguration')->findOneBy([
                'user'  => $this->getUser(),
                'id'    => $configId
            ]);

        if(!$config)
            throw $this->createNotFoundException("Unknown exportation configuration");

        $blob->setConfigId($config->getId());
        $this->get('session')->set($blob->getKey(), serialize($blob));
        return $this->redirectToRoute('netbs.core.export.check_settings', ['blobKey' => $blob->getKey()]);
    }

    /**
     * @Route("/remove-config/{blobKey}/{configId}", name="netbs.core.export.remove_config")
     * @param $blobKey
     * @param $configId
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function removeConfigAction($blobKey, $configId, EntityManagerInterface $em, ExporterManager $manager) {

        /** @var ExportBlob $blob */
        $blob   = unserialize($this->get('session')->get($blobKey));
        $export = $manager->getExporterByAlias($blob->getExporterAlias());
        $config = $em->getRepository('NetBSCoreBundle:ExportConfiguration')->findOneBy([
            'user'  => $this->getUser(),
            'id'    => $configId
        ]);

        if(!$config)
            throw $this->createAccessDeniedException();

        $em->remove($config);
        $em->flush();

        $configs    = $this->getUserConfigurations($export, $em);
        $config     = $configs[0];

        $blob->setConfigId($config->getId());
        $this->get('session')->set($blob->getKey(), serialize($blob));

        return $this->redirectToRoute('netbs.core.export.check_settings', ['blobKey' => $blob->getKey()]);
    }

    /**
     * @Route("/check-settings/{blobKey}", name="netbs.core.export.check_settings")
     * @param Request $request
     * @param $blobKey
     * @return Response
     */
    public function exportSettingsViewAction(Request $request, $blobKey, EntityManagerInterface $em, ExporterManager $manager) {

        /** @var ExportBlob $blob */
        /** @var ConfigurableExporterInterface $exporter */
        $blob               = unserialize($this->get('session')->get($blobKey));
        $exporter           = $manager->getExporterByAlias($blob->getExporterAlias());
        $configs            = $this->getUserConfigurations($exporter, $em);
        $configContainer    = $em->find('NetBSCoreBundle:ExportConfiguration', $blob->getConfigId());
        $form               = $this->createForm($exporter->getConfigFormClass(), $configContainer->getConfiguration());

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {

            $config = $form->getData();
            $configContainer->setConfiguration($config);
            $em->persist($configContainer);
            $em->flush();

            $this->addFlash('success', 'Configuration enregistrée');
            return $this->redirectToRoute('netbs.core.export.check_settings', array('blobKey' => $blob->getKey()));
        }

        return $this->render('@NetBSCore/export/check_export.html.twig', [
            'form'      => $form->createView(),
            'exporter'  => $exporter,
            'configs'   => $configs,
            'blob'      => $blob
        ]);
    }

    /**
     * @param $blobKey
     * @return Response
     * @throws \Exception
     * @Route("/preview/{blobKey}", name="netbs.core.export.preview")
     */
    public function previewExportAction($blobKey, ExporterManager $manager, PreviewerManager $previewerManager, EntityManagerInterface $em, LoaderManager $loaderManager, ListBridgeManager $listBridgeManager) {

        $session    = $this->get('session');
        $blob       = unserialize($session->get($blobKey));
        $items      = $this->getItems($blob, $manager, $em, $loaderManager, $listBridgeManager);
        $exporter   = $manager->getExporterByAlias($blob->getExporterAlias());

        if(!$exporter instanceof ConfigurableExporterInterface)
            throw new \Exception("Cant preview file, exporter doesnt support previewing");

        if(!$exporter->getPreviewer())
            return new Response();

        $this->configureExporter($exporter, $blob, $em);
        $previewer  = $previewerManager->getPreviewer($exporter->getPreviewer());
        return $previewer->preview($items, $exporter);
    }


    /**
     * @param $blobKey
     * @return Response
     * @Route("/generate-export/{blobKey}", name="netbs.core.export.generate")
     */
    public function generateExportAction($blobKey, ExporterManager $manager, EntityManagerInterface $em, LoaderManager $loaderManager, ListBridgeManager $listBridgeManager) {

        /** @var ExportBlob $blob */
        $session        = $this->get('session');
        $blob           = unserialize($session->get($blobKey));

        $items          = $this->getItems($blob, $manager, $em, $loaderManager, $listBridgeManager);
        $exporter       = $manager->getExporterByAlias($blob->getExporterAlias());

        $this->configureExporter($exporter, $blob, $em);

        return $exporter->export($items);
    }

    /**
     * @param ExporterInterface $exporter
     * @param ExportBlob $blob
     */
    protected function configureExporter(ExporterInterface $exporter, ExportBlob $blob, EntityManagerInterface $em) {
        if($exporter instanceof ConfigurableExporterInterface) {
            $config    = $em->find('NetBSCoreBundle:ExportConfiguration', $blob->getConfigId());
            $exporter->setConfig($config->getConfiguration());
        }
    }

    /**
     * @param ExportBlob $blob
     * @return array
     */
    protected function getItems(ExportBlob $blob, ExporterManager $manager, EntityManagerInterface $em, LoaderManager $loaders, ListBridgeManager $listBridgeManager) {

        $listItems      = $blob->getIds();
        $exporter       = $manager->getExporterByAlias($blob->getExporterAlias());
        $elements       = [];

        if ($loaders->hasLoader($blob->getItemsClass())) {
            $loader = $loaders->getLoader($blob->getItemsClass());
            $elements   = array_map(function($id) use ($loader) {
                return $loader->fromId($id);
            }, $listItems);
        } else {
            $query = $em->createQueryBuilder();
            $elements = $query->select('x')
                ->from($blob->getItemsClass(), 'x')
                ->where($query->expr()->in('x.id', ':ids'))
                ->setParameter('ids', $listItems)
                ->getQuery()
                ->execute();
        }

        return $listBridgeManager->convertItems($elements, $exporter->getExportableClass());
    }

    /**
     * @param ConfigurableExporterInterface $exporter
     * @return ExportConfiguration[]
     */
    protected function getUserConfigurations(ConfigurableExporterInterface $exporter, EntityManagerInterface $em) {

        $user   = $this->getUser();
        $repo   = $em->getRepository('NetBSCoreBundle:ExportConfiguration');

        $configs= $repo->findBy(array(
            'user'          => $user,
            'exporterAlias' => $exporter->getAlias()
        ));

        if(count($configs) == 0) {
            $class = is_array($exporter->getBasicConfig()) ? $exporter->getBasicConfig()[0] : $exporter->getBasicConfig();
            $configs[] = $this->getNewConfig($class, $exporter->getAlias(), $em);
        }

        return $configs;
    }

    /**
     * @param ConfigurableExporterInterface $exporter
     * @param null $model
     * @return ExportConfiguration
     */
    protected function getNewConfig(ExporterConfigInterface $base, $alias, EntityManagerInterface $em) {

        $config = new ExportConfiguration();
        /** @var ExporterConfigInterface $item */

        $config->setUser($this->getUser())
            ->setExporterAlias($alias)
            ->setConfiguration($base)
            ->setNom($base->getName());

        $em->persist($config);
        $em->flush();

        return $config;
    }
}
