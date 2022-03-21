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
     * @ORM\OneToMany(targetEntity="App\Entity\Reservation", mappedBy="cabane")
     */
    protected $reservations;

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
}