<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table(name="apmbs_cabanes")
 * @ORM\Entity()
 */
class Cabane {

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string
     * @Assert\NotBlank()
     * @ORM\Column(name="nom", type="string", length=255)
     */
    protected $nom;

    /**
     * @var string
     * @Assert\NotBlank()
     * @ORM\Column(name="calendar_id", type="string", length=255)
     */
    protected $calendarId;

    /**
     * @var APMBSReservation[]
     * @ORM\OneToMany(targetEntity="App\Entity\APMBSReservation", mappedBy="cabane")
     */
    protected $reservations;

    /**
     * @var string
     * 
     * @Assert\NotBlank()
     * @ORM\Column(name="location", type="string", length=255)
     */
    protected $location;

    /**
     * @var string
     * 
     * @ORM\Column(name="intendance", type="text", nullable=true)
     */
    protected $intendance;

    /**
     * @var string
     *
     * @ORM\Column(name="demande_recue_text", type="text", nullable=true)
     */
    protected $demandeRecueText;

    /**
     * @var string
     *
     * @ORM\Column(name="demande_refusee_text", type="text", nullable=true)
     */
    protected $demandeRefuseeText;

    /**
     * @var string
     *
     * @ORM\Column(name="demande_annulee_text", type="text", nullable=true)
     */
    protected $demandeAnnuleeText;

    /**
     * @var string
     *
     * @ORM\Column(name="demande_acceptee_text", type="text", nullable=true)
     */
    protected $demandeAccepteeText;

    /**
     * @var string
     *
     * @ORM\Column(name="demande_modifiee_text", type="text", nullable=true)
     */
    protected $demandeModifieeText;

    /**
     * @var bool
     *
     * @ORM\Column(name="enabled", type="boolean")
     */
    protected $enabled = true;

    public function __construct() {
        $this->reservations = new ArrayCollection();
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getNom()
    {
        return $this->nom;
    }

    /**
     * @param string $nom
     */
    public function setNom(string $nom)
    {
        $this->nom = $nom;
    }

    /**
     * @return string
     */
    public function getCalendarId()
    {
        return $this->calendarId;
    }

    /**
     * @param string $calendarId
     */
    public function setCalendarId(string $calendarId)
    {
        $this->calendarId = $calendarId;
    }

    /**
     * @return APMBSReservation[]
     */
    public function getReservations()
    {
        return $this->reservations;
    }

    /**
     * @return string
     */
    public function getLocation()
    {
        return $this->location;
    }

    /**
     * @param string $location
     */
    public function setLocation(string $location)
    {
        $this->location = $location;
    }

    /**
     * @return string
     */
    public function getIntendance()
    {
        return $this->intendance;
    }

    /**
     * @param string $location
     */
    public function setIntendance(string $intendance)
    {
        $this->intendance = $intendance;
    }

    /**
     * @return string
     */
    public function getDemandeRecueText()
    {
        return $this->demandeRecueText;
    }

    /**
     * @param string $demandeRecueText
     */
    public function setDemandeRecueText(string $demandeRecueText)
    {
        $this->demandeRecueText = $demandeRecueText;
    }

    /**
     * @return string
     */
    public function getDemandeRefuseeText()
    {
        return $this->demandeRefuseeText;
    }

    /**
     * @param string $demandeRefuseeText
     */
    public function setDemandeRefuseeText(string $demandeRefuseeText)
    {
        $this->demandeRefuseeText = $demandeRefuseeText;
    }

    /**
     * @return string
     */
    public function getDemandeAnnuleeText()
    {
        return $this->demandeAnnuleeText;
    }

    /**
     * @param string $demandeAnnuleeText
     */
    public function setDemandeAnnuleeText(string $demandeAnnuleeText)
    {
        $this->demandeAnnuleeText = $demandeAnnuleeText;
    }

    /**
     * @return string
     */
    public function getDemandeAccepteeText()
    {
        return $this->demandeAccepteeText;
    }

    /**
     * @param string $demandeAccepteeText
     */
    public function setDemandeAccepteeText(string $demandeAccepteeText)
    {
        $this->demandeAccepteeText = $demandeAccepteeText;
    }

    /**
     * @return string
     */
    public function getDemandeModifieeText()
    {
        return $this->demandeModifieeText;
    }

    /**
     * @param string $demandeModifieeText
     */
    public function setDemandeModifieeText(string $demandeModifieeText)
    {
        $this->demandeModifieeText = $demandeModifieeText;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param bool $enabled
     */
    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }
}