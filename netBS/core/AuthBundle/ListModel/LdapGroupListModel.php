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
 * projection's group outlines. Each group links to its own page listing the members.
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
                    $url = htmlspecialchars($this->router->generate('auth.admin.ldap.group', ['key' => $row->key]), ENT_QUOTES, 'UTF-8');
                    $html = "<a href=\"{$url}\">" . htmlspecialchars($row->name, ENT_QUOTES, 'UTF-8') . '</a>';
                    if ($row->groupeId !== null) {
                        $groupeUrl = htmlspecialchars($this->router->generate('netbs.fichier.groupe.page_groupe', ['id' => $row->groupeId]), ENT_QUOTES, 'UTF-8');
                        $html .= " <a href=\"{$groupeUrl}\" class=\"text-muted\" title=\"Groupe netBS\"><i class=\"fas fa-external-link-alt\"></i></a>";
                    }
                    return $html;
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
                // Counted on hover (lazy-count controller): resolving every group is slow.
                ClosureColumn::CLOSURE => function (LdapGroupRow $row) {
                    $url = htmlspecialchars($this->router->generate('auth.admin.ldap.group_count', ['key' => $row->key]), ENT_QUOTES, 'UTF-8');
                    return "<span class=\"ldap-member-count text-muted\" data-lazy-count-url=\"{$url}\" title=\"Survoler la ligne pour compter\">…</span>";
                },
            ]);
    }
}
