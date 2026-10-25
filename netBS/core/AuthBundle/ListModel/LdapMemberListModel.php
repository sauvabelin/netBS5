<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\ListModel;

use NetBS\CoreBundle\Utils\Traits\RouterTrait;
use NetBS\ListBundle\Column\ClosureColumn;
use NetBS\ListBundle\Model\BaseListModel;
use NetBS\ListBundle\Model\ListColumnsConfiguration;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** Members of one LDAP group, compared with what lldap currently holds. */
final class LdapMemberListModel extends BaseListModel
{
    use RouterTrait;

    public const MEMBERS = 'members';

    private const STATUS_BADGES = [
        LdapMemberRow::UP_TO_DATE => 'text-bg-light',
        LdapMemberRow::TO_ADD => 'text-bg-success',
        LdapMemberRow::TO_REMOVE => 'text-bg-danger',
    ];

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setRequired(self::MEMBERS);
    }

    protected function buildItemsList()
    {
        return $this->getParameter(self::MEMBERS);
    }

    public function getManagedItemsClass()
    {
        return LdapMemberRow::class;
    }

    public function getAlias()
    {
        return 'netbs.auth.ldap.members';
    }

    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        // The netbs renderer prints cells raw, so every value is escaped here.
        $configuration
            ->addColumn('Nom', null, ClosureColumn::class, [
                ClosureColumn::CLOSURE => function (LdapMemberRow $row) {
                    $name = htmlspecialchars($row->displayName, ENT_QUOTES, 'UTF-8');
                    if ($row->membreId !== null) {
                        $url = htmlspecialchars($this->router->generate('netbs.fichier.membre.page_membre', ['id' => $row->membreId]), ENT_QUOTES, 'UTF-8');
                        $name = "<a href=\"{$url}\">{$name}</a>";
                    }
                    return $name;
                },
            ])
            ->addColumn('Utilisateur', null, ClosureColumn::class, [
                ClosureColumn::CLOSURE => fn (LdapMemberRow $row) => '<code>' . htmlspecialchars($row->uid, ENT_QUOTES, 'UTF-8') . '</code>',
            ])
            ->addColumn('Annuaire', null, ClosureColumn::class, [
                ClosureColumn::CLOSURE => fn (LdapMemberRow $row) => $row->status === null
                    ? '—'
                    : sprintf('<span class="badge %s">%s</span>', self::STATUS_BADGES[$row->status], $row->status),
            ]);
    }
}
