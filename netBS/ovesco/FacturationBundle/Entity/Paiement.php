<?php

namespace Ovesco\FacturationBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use Genkgo\Camt\DTO\EntryTransactionDetail;
use NetBS\FichierBundle\Utils\Entity\RemarqueTrait;
use NetBS\FichierBundle\Utils\FichierHelper;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Paiement
 *
 * @ORM\Table(name="ovesco_facturation_paiements")
 * @ORM\Entity
 */
class Paiement
{
    use TimestampableEntity, RemarqueTrait;

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var float
     *
     * @ORM\Column(name="montant", type="float")
     * @Groups({"default"})
     */
    protected $montant;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="date", type="datetime")
     * @Groups({"default"})
     */
    protected $date;

    /**
     * @var Facture
     *
     * @ORM\ManyToOne(targetEntity="Ovesco\FacturationBundle\Entity\Facture", inversedBy="paiements")
     * @Groups({"paiement_with_facture"})
     */
    protected $facture;

    /**
     * @var Compte
     *
     * @ORM\ManyToOne(targetEntity="Ovesco\FacturationBundle\Entity\Compte")
     * @Groups({"paiement_with_compte"})
     */
    protected $compte;

    /**
     * @var string
     *
     * @ORM\Column(name="transactionDetails", type="text", nullable=true)
     */
    protected $transactionDetails;

    public function __construct()
    {
        $this->date = new \DateTime();
    }

    /**
     * Get id.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set montant.
     *
     * @param float $montant
     *
     * @return Paiement
     */
    public function setMontant($montant)
    {
        $this->montant = floatval($montant);

        return $this;
    }

    /**
     * Get montant.
     *
     * @return float
     */
    public function getMontant()
    {
        return $this->montant;
    }

    /**
     * Set facture.
     *
     * @param \Ovesco\FacturationBundle\Entity\Facture|null $facture
     *
     * @return Paiement
     */
    public function setFacture(Facture $facture = null)
    {
        $this->facture = $facture;

        return $this;
    }

    /**
     * Get facture.
     *
     * @return \Ovesco\FacturationBundle\Entity\Facture|null
     */
    public function getFacture()
    {
        return $this->facture;
    }

    /**
     * @return Compte
     */
    public function getCompte()
    {
        return $this->compte;
    }

    /**
     * @param Compte $compte
     * @return Paiement
     */
    public function setCompte($compte)
    {
        $this->compte = $compte;
        return $this;
    }

    /**
     * @return \DateTime
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * @param \DateTime $date
     * @return Paiement
     */
    public function setDate($date)
    {
        $this->date = $date;
        return $this;
    }

    /**
     * @return EntryTransactionDetail
     */
    public function getTransactionDetails($array = false)
    {
        if ($this->transactionDetails) {
            $result = unserialize($this->transactionDetails);
            return $array ? FichierHelper::arrayToString(FichierHelper::objectToArray($result)) : $result;
        }
        return null;
    }

    /**
     * @param EntryTransactionDetail $transactionDetails
     * @return Paiement
     */
    public function setTransactionDetails($transactionDetails)
    {
        $this->transactionDetails = serialize($transactionDetails);

        return $this;
    }

    /**
     * @return \DateTime|\DateTimeImmutable
     */
    public function getDateEffectivePaiement() {

        return $this->getTransactionDetails()
            ? $this->getTransactionDetails()->getRelatedDates()->getAcceptanceDateTime()
            : $this->date;
    }

    public function __toString()
    {
        return "paiement_" . $this->getId();
    }
}
