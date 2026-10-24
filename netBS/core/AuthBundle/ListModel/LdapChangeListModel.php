<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\ListModel;

use NetBS\ListBundle\Column\ClosureColumn;
use NetBS\ListBundle\Model\BaseListModel;
use NetBS\ListBundle\Model\ListColumnsConfiguration;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Pending changes on the LDAP page (dry-run report of the directory sync). */
final class LdapChangeListModel extends BaseListModel
{
    public const CHANGES = 'changes';

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired(self::CHANGES);
    }

    protected function buildItemsList()
    {
        return $this->getParameter(self::CHANGES);
    }

    public function getManagedItemsClass()
    {
        return LdapChangeRow::class;
    }

    public function getAlias()
    {
        return 'netbs.auth.ldap.changes';
    }

    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration->addColumn('Changement', null, ClosureColumn::class, [
            ClosureColumn::CLOSURE => fn (LdapChangeRow $row) => '<code>' . htmlspecialchars($row->change, ENT_QUOTES, 'UTF-8') . '</code>',
        ]);
    }
}
