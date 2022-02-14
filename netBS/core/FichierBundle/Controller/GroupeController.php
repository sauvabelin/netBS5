<?php

namespace NetBS\FichierBundle\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query\ResultSetMapping;
use NetBS\CoreBundle\Block\CardBlock;
use NetBS\CoreBundle\Block\LayoutManager;
use NetBS\CoreBundle\Block\Model\Tab;
use NetBS\CoreBundle\Block\TabsCardBlock;
use NetBS\CoreBundle\Block\TemplateBlock;
use NetBS\CoreBundle\Form\Type\DatepickerType;
use NetBS\CoreBundle\Form\Type\SwitchType;
use NetBS\CoreBundle\Utils\Modal;
use NetBS\FichierBundle\Form\GroupeType;
use NetBS\FichierBundle\Mapping\BaseAttribution;
use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\FichierBundle\Mapping\Personne;
use NetBS\FichierBundle\Service\FichierConfig;
use NetBS\SecureBundle\Voter\CRUD;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class GroupeController
 */
class GroupeController extends AbstractController
{
    protected $config;

    public function __construct(FichierConfig $config)
    {
        $this->config = $config;
    }

    protected function getGroupeClass() {
        return $this->config->getGroupeClass();
    }

    /**
     * @param Request $request
     * @param $id
     * @Route("/groupe/statistics/effectifs/{id}", name="netbs.fichier.groupe.statistics_effectifs")
     * @throws \Exception
     */
    public function getGroupeEffectifsStats(Request $request, $id, EntityManagerInterface $em) {
        $begin = new \DateTime($request->get('begin'));
        $end = new \DateTime($request->get('end'));

        $steps = $request->get('steps') ?: 50;
        $diff = $end->getTimestamp() - $begin->getTimestamp();
        $groupClass = $this->config->getGroupeClass();

        $rsm = new ResultSetMapping();
        $rsm->addEntityResult($groupClass, 'u');
        $rsm->addFieldResult('u', 'id', 'id');
        $tableName = $em->getClassMetadata($groupClass)->getTableName();

        $query = $em->createNativeQuery("
select  id,
        parent_id
from    (select * from $tableName
         order by parent_id, id) $tableName,
        (select @pv := ?) initialisation
where   find_in_set(parent_id, @pv) > 0
and     @pv := concat(@pv, ',', id)
        ", $rsm);

        $query->setParameter(1, $id);
        $groupIds = array_merge([$id], array_map(function($ar) { return $ar['id'];}, $query->getArrayResult()));
        $attributions = $em->createQueryBuilder()
            ->from($this->config->getAttributionClass(), 'a')
            ->select('a, m')
            ->where('a.groupe IN (:gids)')
            ->setParameter('gids', $groupIds)
            ->leftJoin('a.membre', 'm')
            ->getQuery()
            ->getResult();

        $stats = [];
        for ($i = 0; $i < intval($steps); $i++) {
            $palier = (int)ceil(($diff / $steps) * $i);
            $date = ((new \DateTime('@' . ($begin->getTimestamp() + $palier)))->setTimezone($begin->getTimezone()));
            $aInt = $date->getTimestamp();
            $found = [];
            $pallierData  = [
                'pallier' => $date,
                'countHomme' => 0,
                'countAll' => 0,
            ];

            /** @var BaseAttribution $attribution */
            foreach($attributions as $attribution) {
                $aBegin = $attribution->getDateDebut()->getTimestamp();
                $aEnd = $attribution->getDateFin();
                if(!($aBegin <= $aInt && ($aEnd === null || $aEnd->getTimestamp() >= $aInt))) continue;
                if(in_array($attribution->getMembreId(), $found)) continue;
                $found[] = $attribution->getMembreId();
                $pallierData['countAll']++;
                if ($attribution->getMembre()->getSexe() === Personne::HOMME)
                    $pallierData['countHomme']++;
            }

            $stats[] = $pallierData;
        }

        return new JsonResponse($stats);
    }

    /**
     * @param Request $request
     * @Route("/modal/add", name="netbs.fichier.groupe.modal_add")
     * @return \Symfony\Component\HttpFoundation\Response
     * @Security("is_granted('ROLE_CREATE_EVERYWHERE')")
     */
    public function addGroupeModalAction(Request $request, EntityManagerInterface $em) {

        $gclass         = $this->getGroupeClass();
        $groupe         = new $gclass();
        $form           = $this->createForm(GroupeType::class, $groupe);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()) {
            $em->persist($form->getData());
            $em->flush();

            $this->addFlash("success", "Groupe {$groupe->getNom()} ajouté!");
            return Modal::refresh();
        }

        return $this->render('@NetBSFichier/generic/add_generic.modal.twig', [
            'form'  => $form->createView()
        ], Modal::renderModal($form));
    }

    /**
     * @Route("/groupes", name="netbs.fichier.groupe.page_groupes_hierarchy")
     * @Security("is_granted('ROLE_READ_EVERYWHERE')")
     * @return Response
     */
    public function pageGroupesHierarchyAction(EntityManagerInterface $em) {

        $repo       = $em->getRepository($this->getGroupeClass());

        /** @var BaseGroupe[] $groupes */
        $groupes    = $repo->findAll();
        $types      = $em->getRepository($this->config->getGroupeTypeClass())->findAll();
        $categories = $em->getRepository($this->config->getGroupeCategorieClass())->findAll();

        return $this->render('@NetBSFichier/groupe/page_groupes_hierarchy.html.twig', array(
            'groupes'       => $groupes,
            'types'         => $types,
            'categories'    => $categories
        ));
    }

    /**
     * @Route("/groupe/{id}", name="netbs.fichier.groupe.page_groupe")
     * @return Response
     */
    public function pageGroupeAction($id, EntityManagerInterface $em, LayoutManager $layout) {

        /** @var BaseGroupe $groupe */
        $class  = $this->getGroupeClass();
        $groupe = $em->find($class, $id);

        if(!$groupe)
            throw $this->createNotFoundException();

        if(!$this->isGranted(CRUD::READ, $groupe))
            throw $this->createAccessDeniedException("Vous n'avez pas les accès requis pour consulter ce groupe");

        $form   = $this->createForm(GroupeType::class, $groupe)->createView();
        $config = $layout::configurator();

        $tabs   = [
            new Tab('Effectifs', '@NetBSFichier/groupe/list_attributions.tab.twig', [
                'groupe'    => $groupe,
                'list'      => 'netbs.fichier.groupe.attributions'
            ])
        ];

        if($groupe->getGroupeType()->getAffichageEffectifs())
            foreach($groupe->getEnfants() as $enfant)
                $tabs[] = new Tab($enfant->getNom(), '@NetBSFichier/groupe/list_attributions.tab.twig', [
                    'groupe'    => $enfant,
                    'list'      => 'netbs.fichier.groupe.attributions'
                ]);

        $config
            ->addRow()
                ->pushColumn(3)
                    ->addRow()
                        ->pushColumn(12)
                            ->setBlock(CardBlock::class, [
                                'template'  => "@NetBSFichier/groupe/presentation.block.twig",
                                'title'     => $groupe->getNom(),
                                'subtitle'  => $groupe->getGroupeType()->getNom() . ' - ' . $groupe->getGroupeType()->getGroupeCategorie()->getNom(),
                                'params'    => [
                                    'form'  => $form
                                ]
                            ])
                        ->close()
                        ->pushColumn(12)
                            ->addRow()
                                ->pushColumn(12)
                                    ->setBlock(TemplateBlock::class, [
                                        'template'  => '@NetBSFichier/groupe/children.block.twig',
                                        'params'    => [
                                            'groupe'    => $groupe
                                        ]
                                    ])
                                ->close()
                            ->close()
                        ->close()
                    ->close()
                ->close()
                ->pushColumn(9)
                    ->addRow()
                        ->pushColumn(12)
                            ->setBlock(TabsCardBlock::class, [
                                'tabs'  => $tabs,
                                'table' => true
                            ])
                        ->close()
                    ->close()
                ->close()
            ->close()
        ;

        if ($this->isGranted('ROLE_SG')) {

            $now = new \DateTime();
            $form = $this->createFormBuilder([
                'begin' => new \DateTime('@' . ($now->getTimestamp() - (3600*24*365))),
                'steps' => 50,
                'end'   => $now,
                'total'   => true,
                'hommes' => true,
                'femmes' => true,
            ]);
            $form->add('begin', DatepickerType::class, ['label' => 'Date de début'])
                ->add('steps', NumberType::class, ['label' => 'Nombre de points'])
                ->add('end', DatepickerType::class, ['label' => 'Date de fin'])
                ->add('total', SwitchType::class, ['label' => 'Total', 'required' => false])
                ->add('hommes', SwitchType::class, ['label' => 'Hommes', 'required' => false])
                ->add('femmes', SwitchType::class, ['label' => 'Femmes', 'required' => false])
            ;

            $config->getRow(0)->getColumn(1)->addRow()->pushColumn(12)->setBlock(CardBlock::class, [
                'title' => 'Statistiques',
                'subtitle' => 'Effectifs au cours du temps',
                'template' => '@NetBSFichier/groupe/statistics.block.twig',
                'params' => ['groupe' => $groupe, 'statsForm' => $form->getForm()->createView()],
            ]);
        }

        return $layout->renderResponse('netbs', $config, [
            'title' => $groupe->getNom(),
            'item'  => $groupe
        ]);
    }
}
