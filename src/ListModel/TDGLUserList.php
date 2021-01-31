<?php

namespace App\ListModel;

use NetBS\CoreBundle\Form\Type\SwitchType;
use NetBS\CoreBundle\ListModel\Column\XEditableColumn;
use NetBS\ListBundle\Model\ListColumnsConfiguration;
use NetBS\SecureBundle\ListModel\UsersList;
use Symfony\Component\Form\Extension\Core\Type\NumberType;

class TDGLUserList extends UsersList
{
    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        parent::configureColumns($configuration);
        $actions    = $configuration->removeColumn(count($configuration->getColumns()) - 1);

        $configuration

            ->addColumn('Accès nextcloud', null, XEditableColumn::class, [
                XEditableColumn::PROPERTY       => 'nextcloudAccount',
                XEditableColumn::TYPE_CLASS     => SwitchType::class
            ])
            ->addColumn('Quota nextcloud', null, XEditableColumn::class, [
                XEditableColumn::PROPERTY       => 'nextcloudQuota',
                XEditableColumn::TYPE_CLASS     => NumberType::class
            ])
            ->addColumn('Accès au wiki', null, XEditableColumn::class, [
                XEditableColumn::PROPERTY   => 'wikiAccount',
                XEditableColumn::TYPE_CLASS => SwitchType::class
            ])
            ->addColumn('Admin wiki', null, XEditableColumn::class, [
                XEditableColumn::PROPERTY   => 'wikiAdmin',
                XEditableColumn::TYPE_CLASS => SwitchType::class
            ])
            ->addColumn($actions->getHeader(), $actions->getAccessor(), $actions->getClass(), $actions->getParams())
        ;
    }
}
