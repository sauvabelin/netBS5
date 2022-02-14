<?php

namespace Ovesco\FacturationBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use NetBS\FichierBundle\Utils\Entity\RemarqueTrait;
use Ovesco\FacturationBundle\Util\DateImpressionTrait;
use Ovesco\FacturationBundle\Util\DebiteurTrait;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Facture
 *
 * @ORM\Table(name="ovesco_facturation_factures")
 * @ORM\Entity(repositoryClass="Ovesco\FacturationBundle\Repository\FactureRepository")
 */
class Facture
{
    const PAYEE     = 'payee';
    const OUVERTE   = 'ouverte';
    const ANNULEE   = 'annulee';

    use TimestampableEntity, RemarqueTrait, DebiteurTrait, DateImpressionTrait;

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Groups({"default"})
     */
    protected $id;

    /**
     * @var int
     * @ORM\Column(name="old_fichier_id", type="integer")
     * @Groups({"default"})
     */
    protected $oldFichierId = -1;

    /**
     * @var string
     *
     * @ORM\Column(name="statut", type="string", length=255)
     * @Groups({"default"})
     */
    protected $statut = self::OUVERTE;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="date", type="datetime")
     * @Groups({"default"})
     */
    protected $date;

    /**
     * @var Creance[]
     *
     * @ORM\OneToMany(targetEntity="Creance", mappedBy="facture", fetch="EAGER", cascade={"persist", "remove"})
     * @Groups({"facture_with_creances"})
     */
    protected $creances;

    /**
     * @var Rappel[]
     *
     * @ORM\OneToMany(targetEntity="Rappel", mappedBy="facture", cascade={"persist", "remove"}, fetch="EAGER")
     * @Groups({"default"})
     */
    protected $rappels;

    /**
     * @var Paiement[]
     *
     * @ORM\OneToMany(targetEntity="Paiement", mappedBy="facture", cascade={"persist", "remove"}, fetch="EAGER")
     * @Groups({"facture_with_paiements"})
     */
    protected $paiements;

    /**
     * @var Compte
     *
     * @ORM\ManyToOne(targetEntity="Ovesco\FacturationBundle\Entity\Compte")
     * @Groups({"default"})
     */
    protected $compteToUse;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->creances = new ArrayCollection();
        $this->rappels = new ArrayCollection();
        $this->paiements = new ArrayCollection();
        $this->date = new \DateTime();
    }

    public function __toString()
    {
        return "#{$this->getFactureId()} pour " . $this->debiteur->__toString();
    }

    public static function getStatutChoices() {
        return [
            'payée' => self::PAYEE,
            'ouverte' => self::OUVERTE,
            'annulée' => self::ANNULEE,
        ];
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

    public function getFactureId() {
        return $this->oldFichierId === -1 ? $this->id : $this->oldFichierId;
    }

    /**
     * @return int
     */
    public function _getOldFichierId()
    {
        return $this->oldFichierId;
    }

    /**
     * @param int $oldFichierId
     */
    public function _setOldFichierId($oldFichierId)
    {
        $this->oldFichierId = $oldFichierId;
    }

    /**
     * Set statut.
     *
     * @param string $statut
     *
     * @return Facture
     */
    public function setStatut($statut)
    {
        $this->statut = $statut;

        return $this;
    }

    /**
     * Get statut.
     *
     * @return string
     */
    public function getStatut()
    {
        return $this->statut;
    }

    /**
     * Add creance.
     *
     * @param \Ovesco\FacturationBundle\Entity\Creance $creance
     *
     * @return Facture
     */
    public function addCreance(Creance $creance)
    {
        $this->creances[] = $creance;
        $creance->setFacture($this);
        return $this;
    }

    /**
     * Remove creance.
     *
     * @param \Ovesco\FacturationBundle\Entity\Creance $creance
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeCreance(Creance $creance)
    {
        $creance->setFacture(null);
        return $this->creances->removeElement($creance);
    }

    /**
     * Get creances.
     *
     * @return Creance[]
     */
    public function getCreances()
    {
        return $this->creances;
    }

    /**
     * Add rappel.
     *
     * @param \Ovesco\FacturationBundle\Entity\Rappel $rappel
     *
     * @return Facture
     */
    public function addRappel(Rappel $rappel)
    {
        $this->rappels[] = $rappel;
        $rappel->setFacture($this);
        return $this;
    }

    /**
     * Remove rappel.
     *
     * @param \Ovesco\FacturationBundle\Entity\Rappel $rappel
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removeRappel(Rappel $rappel)
    {
        return $this->rappels->removeElement($rappel);
    }

    /**
     * Get rappels.
     *
     * @return \Doctrine\Common\Collections\Collection
     */
    public function getRappels()
    {
        return $this->rappels;
    }

    /**
     * Add paiement.
     *
     * @param \Ovesco\FacturationBundle\Entity\Paiement $paiement
     *
     * @return Facture
     */
    public function addPaiement(Paiement $paiement)
    {
        $this->paiements[] = $paiement;
        $paiement->setFacture($this);

        if ($this->getMontantEncoreDu() <= 0) $this->setStatut(self::PAYEE);
        else if($this->statut !== self::ANNULEE) $this->setStatut(self::OUVERTE);
        return $this;
    }

    /**
     * Remove paiement.
     *
     * @param \Ovesco\FacturationBundle\Entity\Paiement $paiement
     *
     * @return boolean TRUE if this collection contained the specified element, FALSE otherwise.
     */
    public function removePaiement(Paiement $paiement)
    {
        $res = $this->paiements->removeElement($paiement);
        if ($this->getStatut() === Facture::PAYEE && $this->getMontantEncoreDu() > 0)
            $this->setStatut(Facture::OUVERTE);
        return $res;
    }

    /**
     * Get paiements.
     *
     * @return Paiement[]
     */
    public function getPaiements()
    {
        return $this->paiements;
    }

    /**
     * @return Paiement|null
     */
    public function getLatestPaiement() {
        $paiements = $this->paiements->toArray();
        usort($paiements, function(Paiement $a, Paiement $b) {
            if (!$a->getDateEffectivePaiement()) return 1;
            if (!$b->getDateEffectivePaiement()) return -1;
            return $a->getDateEffectivePaiement() > $b->getDateEffectivePaiement() ? 1 : -1;
        });
        return array_pop($paiements);
    }

    /**
     * @return Compte
     */
    public function getCompteToUse()
    {
        return $this->compteToUse;
    }

    /**
     * @param Compte $compteToUse
     */
    public function setCompteToUse($compteToUse)
    {
        $this->compteToUse = $compteToUse;
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
     */
    public function setDate($date)
    {
        $this->date = $date;
    }

    public function getMontant() {
        return array_reduce($this->creances->toArray(), function($montant, Creance $creance) {
            return $montant + $creance->getActualMontant();
        }, 0);
    }

    public function getMontantPaye() {
        return array_reduce($this->paiements->toArray(), function($montant, Paiement $paiement) {
            return $montant + $paiement->getMontant();
        }, 0);
    }

    public function getMontantEncoreDu() {
        return $this->getMontant() - $this->getMontantPaye();
    }

    public function setLatestImpression(\DateTime $date) {
        $rappel = $this->getLatestRappel();
        if ($rappel && $rappel->getDateImpression() === null) $rappel->setDateImpression($date);
        elseif ($this->getDateImpression() === null) $this->setDateImpression($date);
    }

    /**
     * @return Rappel[]
     */
    public function sortRappelsByImpression() {
        $rappels = $this->rappels->toArray();
        usort($rappels, function(Rappel $a, Rappel $b) {
            if (!$a->getDateImpression() && !$b->getDateImpression())
                return $a->getDate() > $b->getDate() ? 1 : -1;
            if (!$a->getDateImpression()) return 1;
            if (!$b->getDateImpression()) return -1;
            return $a->getDateImpression() > $b->getDateImpression() ? 1 : -1;
        });
        return $rappels;
    }

    /**
     * @return Rappel|null
     */
    public function getLatestRappel() {
        $rappels = $this->sortRappelsByImpression();
        return array_pop($rappels);
    }

    public function getLatestImpression() {
        $sortedASC = $this->sortRappelsByImpression();
        $sortedDSC = array_reverse($sortedASC);
        foreach($sortedDSC as $rappel)
            if($rappel->getDateImpression())
                return $rappel->getDateImpression();

        return $this->getDateImpression();
    }

    public function hasBeenPrinted() {

        if (count($this->rappels) === 0) return $this->getDateImpression() !== null;
        return $this->getLatestRappel()->getDateImpression() !== null;
    }
}
