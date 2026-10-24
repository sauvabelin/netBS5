<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\ListModel;

use NetBS\CoreBundle\Utils\Traits\RouterTrait;
use NetBS\ListBundle\Column\ClosureColumn;
use NetBS\ListBundle\Model\BaseListModel;
use NetBS\ListBundle\Model\ListColumnsConfiguration;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Groups on the LDAP page, rows built by the controller from the directory
 * snapshot. Members are not rendered; the "Membres" badge loads them through
 * the ldap-members Stimulus controller.
 */
final class LdapGroupListModel extends BaseListModel
{
    use RouterTrait;

    public const ROWS = 'rows';

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired(self::ROWS);
    }

    protected function buildItemsList()
    {
        return $this->getParameter(self::ROWS);
    }

    public function getManagedItemsClass()
    {
        return LdapGroupRow::class;
    }

    public function getAlias()
    {
        return 'netbs.auth.ldap.groups';
    }

    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration
            ->addColumn('Groupe', null, ClosureColumn::class, [
                ClosureColumn::CLOSURE => function (LdapGroupRow $row) {
                    $name = htmlspecialchars($row->name, ENT_QUOTES, 'UTF-8');
                    if ($row->groupeId !== null) {
                        $url = htmlspecialchars($this->router->generate('netbs.fichier.groupe.page_groupe', ['id' => $row->groupeId]), ENT_QUOTES, 'UTF-8');
                        $name = "<a href=\"{$url}\">{$name}</a>";
                    }
                    return $name;
                },
            ])
            // The netbs renderer prints cells raw, so every value is escaped here.
            ->addColumn('Type', null, ClosureColumn::class, [
                ClosureColumn::CLOSURE => fn (LdapGroupRow $row) => htmlspecialchars($row->type, ENT_QUOTES, 'UTF-8'),
            ])
            ->addColumn('Sous-groupes', null, ClosureColumn::class, [
                ClosureColumn::CLOSURE => fn (LdapGroupRow $row) => htmlspecialchars($row->sousGroupes, ENT_QUOTES, 'UTF-8'),
            ])
            ->addColumn('Membres', null, ClosureColumn::class, [
                ClosureColumn::CLOSURE => function (LdapGroupRow $row) {
                    $key = htmlspecialchars($row->key, ENT_QUOTES, 'UTF-8');
                    return "<span class=\"badge text-bg-secondary\" role=\"button\" title=\"Afficher les membres\""
                        . " data-key=\"{$key}\" data-action=\"click->ldap-members#toggle\">{$row->memberCount}</span>";
                },
            ]);
    }
}
