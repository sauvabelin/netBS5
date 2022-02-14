<?php

namespace NetBS\CoreBundle\ListModel;

use NetBS\CoreBundle\Entity\LoggedChange;
use NetBS\CoreBundle\ListModel\Column\HelperColumn;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\CoreBundle\Utils\Traits\NetBSTrait;
use NetBS\CoreBundle\Utils\Traits\ParamTrait;
use NetBS\ListBundle\Column\ClosureColumn;
use NetBS\ListBundle\Column\DateTimeColumn;
use NetBS\ListBundle\Column\SimpleColumn;
use NetBS\ListBundle\Model\BaseListModel;
use NetBS\ListBundle\Model\ListColumnsConfiguration;
use NetBS\SecureBundle\Voter\CRUD;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LoggedChangesList extends BaseListModel
{
    use EntityManagerTrait, ParamTrait;

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired('status');
    }

    /**
     * Retrieves all elements managed by this list
     * @return array
     */
    protected function buildItemsList()
    {
        return $this->entityManager->createQueryBuilder()
            ->select('c')
            ->from('NetBSCoreBundle:LoggedChange', 'c')
            ->where('c.status = :status')
            ->setParameter('status', $this->getParameter('status'))
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->execute();
    }

    /**
     * Returns the class of items managed by this list
     * @return string
     */
    public function getManagedItemsClass()
    {
        return LoggedChange::class;
    }

    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return 'netbs.core.logged_changes';
    }

    /**
     * Configures the list columns
     * @param ListColumnsConfiguration $configuration
     */
    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration
            ->addColumn('Action', 'action', ClosureColumn::class, [
                ClosureColumn::CLOSURE  => function($str) {return $this->getDot($str);}
            ])
            ->addColumn('Élément modifié', 'displayName', SimpleColumn::class)
            ->addColumn('Propriété', 'property', ClosureColumn::class, [
                ClosureColumn::CLOSURE => function($str) {return $str ? "<code>$str</code>" : "-";}
            ])
            ->addColumn('Modifié par', 'user', HelperColumn::class)
            ->addColumn('Date', 'createdAt', DateTimeColumn::class, [
                'format'    => $this->parameterManager->getValue('format', 'php_datetime')
            ])
        ;
    }

    protected function getDot($str) {

        switch($str) {
            case CRUD::UPDATE:
                return "<span class='badge badge-primary'>Modification</span>";
            case CRUD::CREATE:
                return "<span class='badge badge-success'>Insertion</span>";
            case CRUD::DELETE:
                return "<span class='badge badge-danger'>Suppression</span>";
        }
    }
}