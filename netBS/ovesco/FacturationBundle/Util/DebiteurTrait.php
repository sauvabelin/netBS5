<?php

namespace Ovesco\FacturationBundle\Util;

use NetBS\FichierBundle\Mapping\BaseFamille;
use NetBS\FichierBundle\Mapping\BaseMembre;
use Ovesco\FacturationBundle\Subscriber\DoctrineDebiteurSubscriber;
use Symfony\Component\Serializer\Annotation\Groups;

trait DebiteurTrait
{
    /**
     * @var string
     *
     * @ORM\Column(name="debiteur_id", type="string")
     * @Groups({"debiteur_id"})
     */
    protected $debiteurId;

    /**
     * @var BaseMembre|BaseFamille
     * @Groups({"with_debiteur"})
     */
    private $debiteur;

    /**
     * @return BaseFamille|BaseMembre
     */
    public function getDebiteur()
    {
        return $this->debiteur;
    }

    /**
     * @return string
     */
    public function _getDebiteurId() {
        return $this->debiteurId;
    }

    /**
     * @param BaseFamille|BaseMembre $debiteur
     */
    public function setDebiteur($debiteur)
    {
        $this->debiteur     = $debiteur;
        $this->debiteurId   = DoctrineDebiteurSubscriber::createId($debiteur);
    }
}