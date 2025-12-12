<?php

namespace Iacopo\MailingBundle\ListModel;

use Iacopo\MailingBundle\Entity\MailingListAlias;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\CoreBundle\Utils\Traits\RouterTrait;
use NetBS\ListBundle\Column\ClosureColumn;
use NetBS\ListBundle\Model\BaseListModel;
use NetBS\ListBundle\Model\ListColumnsConfiguration;

class MailingAliasListModel extends BaseListModel
{
    use EntityManagerTrait, RouterTrait;

    private $mailingListId;

    public function setMailingListId($id)
    {
        $this->mailingListId = $id;
    }

    protected function buildItemsList()
    {
        if (!$this->mailingListId) {
            return [];
        }

        return $this->entityManager->createQueryBuilder()
            ->select('a')
            ->from(MailingListAlias::class, 'a')
            ->where('a.mailingList = :listId')
            ->setParameter('listId', $this->mailingListId)
            ->orderBy('a.address', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getManagedItemsClass()
    {
        return MailingListAlias::class;
    }

    public function getAlias()
    {
        return "iacopo.mailing.aliases";
    }

    public function configureColumns(ListColumnsConfiguration $configuration)
    {
        $configuration
            ->addColumn("Adresse alternative", null, ClosureColumn::class, [
                ClosureColumn::CLOSURE => function(MailingListAlias $alias) {
                    return "<code>{$alias->getAddress()}</code>";
                }
            ])
            ->addColumn("Actions", null, ClosureColumn::class, [
                ClosureColumn::CLOSURE => function(MailingListAlias $alias) {
                    $deleteUrl = $this->router->generate('iacopo.mailing.alias.delete', ['id' => $alias->getId()]);

                    return "
                        <form method=\"post\" action=\"{$deleteUrl}\" style=\"display:inline\" onsubmit=\"return confirm('Supprimer cette adresse ?')\">
                            <button type=\"submit\" class=\"btn btn-sm btn-danger\" title=\"Supprimer\">
                                <i class=\"fas fa-trash\"></i>
                            </button>
                        </form>
                    ";
                }
            ]);
    }
}
