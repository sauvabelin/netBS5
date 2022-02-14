<?php

namespace Ovesco\FacturationBundle\ListModel;

use NetBS\CoreBundle\ListModel\Column\HelperColumn;
use NetBS\CoreBundle\ListModel\Column\XEditableColumn;
use NetBS\CoreBundle\Utils\Traits\EntityManagerTrait;
use NetBS\ListBundle\Column\DateTimeColumn;
use NetBS\ListBundle\Column\SimpleColumn;
use NetBS\ListBundle\Model\BaseListModel;
use NetBS\ListBundle\Model\ListColumnsConfiguration;
use Ovesco\FacturationBundle\Entity\Facture;
use Ovesco\FacturationBundle\Util\FactureListTrait;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class FacturesAttenteImpressionList extends BaseListModel
{
    use EntityManagerTrait, FactureListTrait;

    /**
     * Retrieves all elements managed by this list
     * @return array
     */
    protected function buildItemsList()
    {
        /** @var Facture[] $factures */
        $factures = $this->entityManager->getRepository('OvescoFacturationBundle:Facture')
            ->findBy(['statut' => Facture::OUVERTE]);
        return array_filter($factures, function(Facture $facture) {
            return !$facture->hasBeenPrinted();
        });
    }

    /**
     * Returns this list's alias
     * @return string
     */
    public function getAlias()
    {
        return 'ovesco.facturation.factures_attente_impression';
    }
}