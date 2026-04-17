<?php

namespace NetBS\CoreBundle\ListModel;

use NetBS\CoreBundle\Entity\AuditLog;
use NetBS\CoreBundle\ListModel\Column\HelperColumn;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\CoreBundle\Utils\Traits\ParamTrait;
use NetBS\ListBundle\Column\ClosureColumn;
use NetBS\ListBundle\Column\DateTimeColumn;
use NetBS\ListBundle\Column\SimpleColumn;
use NetBS\ListBundle\Model\BaseListModel;
use NetBS\ListBundle\Model\ListColumnsConfiguration;

class AuditLogList extends BaseListModel
{
    use EntityManagerTrait, ParamTrait;

    /**
     * Retrieves all elements managed by this list
     * @return array
     */
    protected function buildItemsList()
    {
        return $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(AuditLog::class, 'a')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults(500)
            ->getQuery()
            ->execute();
    }

    /**
     * Returns the class of items managed by this list
     * @return string
     */
    public function getManagedItemsClass()
    {
        return AuditLog::class;
    }

    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return 'netbs.core.audit_log';
    }

    /**
     * Configures the list columns
     * @param ListColumnsConfiguration $configuration
     */
    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration
            ->addColumn('Action', 'action', ClosureColumn::class, [
                ClosureColumn::CLOSURE => function($str) { return $this->getActionBadge($str); }
            ])
            ->addColumn('Type', 'entityShortClass', SimpleColumn::class)
            ->addColumn('Élément', 'displayName', SimpleColumn::class)
            ->addColumn('Propriété', 'property', ClosureColumn::class, [
                ClosureColumn::CLOSURE => function($str) { return $str ? "<code>$str</code>" : "-"; }
            ])
            ->addColumn('Utilisateur', 'user', HelperColumn::class)
            ->addColumn('Date', 'createdAt', DateTimeColumn::class, [
                'format' => $this->parameterManager->getValue('format', 'php_datetime')
            ])
        ;
    }

    protected function getActionBadge($str)
    {
        switch ($str) {
            case AuditLog::ACTION_CREATE:
                return "<span class='badge text-bg-success'>Création</span>";
            case AuditLog::ACTION_UPDATE:
                return "<span class='badge text-bg-primary'>Modification</span>";
            case AuditLog::ACTION_DELETE:
                return "<span class='badge text-bg-danger'>Suppression</span>";
            case AuditLog::ACTION_RESTORE:
                return "<span class='badge text-bg-warning'>Restauration</span>";
            default:
                return "<span class='badge text-bg-secondary'>$str</span>";
        }
    }
}
