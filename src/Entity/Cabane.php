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
     * @var Reservation[]
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
     * @var string[]
     * 
     * @ORM\Column(name="intendance", type="simple_array")
     */
    protected $intendance;

    public function __construct() {
        $this->reservations = new ArrayCollection();
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getNom(): string
    {
        return $this->nom;
    }

    /**
     * @param string $nom
     */
    public function setNom(string $nom): void
    {
        $this->nom = $nom;
    }

    /**
     * @return string
     */
    public function getCalendarId(): string
    {
        return $this->calendarId;
    }

    /**
     * @param string $calendarId
     */
    public function setCalendarId(string $calendarId): void
    {
        $this->calendarId = $calendarId;
    }

    /**
     * @return Reservation[]
     */
    public function getReservations(): iterable
    {
        return $this->reservations;
    }

    /**
     * @return string
     */
    public function getLocation(): string
    {
        return $this->location;
    }

    /**
     * @param string $location
     */
    public function setLocation(string $location): void
    {
        $this->location = $location;
    }

    /**
     * @return string
     */
    public function getIntendance(): array
    {
        return $this->intendance;
    }

    /**
     * @param string $location
     */
    public function setIntendance(array $intendance): void
    {
        $this->intendance = $intendance;
    }
}