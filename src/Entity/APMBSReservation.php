<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @ORM\Table(name="sauvabelin_apmbs_reservations")
 * @ORM\Entity()
 */
class APMBSReservation {

    const PENDING = 'pending';
    const ACCEPTED = 'accepted';
    const REFUSED = 'refused';

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
     * @ORM\Column(name="gc_event_id", type="string", length=255, nullable=true)
     */
    protected $gcEventId;

    /**
     * @var Cabane
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Cabane", inversedBy="reservations")
     */
    protected $cabane;

    /**
     * @var string
     *
     * @ORM\Column(name="status", type="string", length=255)
     */
    protected $status = self::PENDING;

    /**
     * @var \DateTime
     * @Assert\NotBlank()
     * @Assert\Type("\DateTimeInterface")
     * @ORM\Column(name="start", type="datetime")
     */
    protected $start;

    /**
     * @var \DateTime
     * @Assert\NotBlank()
     * @Assert\Type("\DateTimeInterface")
     * @ORM\Column(name="end", type="datetime")
     */
    protected $end;

    /**
     * @var string
     * @Assert\NotBlank()
     * @ORM\Column(name="prenom", type="string", length=255)
     */
    protected $prenom;

    /**
     * @var string
     * @Assert\NotBlank()
     * @ORM\Column(name="nom", type="string", length=255)
     */
    protected $nom;

    /**
     * @var string
     * @Assert\Email
     * @Assert\NotBlank()
     * @ORM\Column(name="email", type="string", length=255)
     */
    protected $email;

    /**
     * @var string
     * @Assert\NotBlank()
     * @ORM\Column(name="phone", type="string", length=255)
     */
    protected $phone;

    /**
     * @var string
     * @Assert\NotBlank()
     * @ORM\Column(name="unite", type="string", length=255)
     */
    protected $unite;

    /**
     * @var string
     * @Assert\NotBlank()
     * @ORM\Column(name="description", type="text")
     */
    protected $description;

    /**
     * @var string
     * @ORM\Column(name="refused_motif", type="text", nullable=true)
     */
    protected $refusedMotif;

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
    public function getGCEventId()
    {
        return $this->gcEventId;
    }

    /**
     * @param string $gcEventId
     */
    public function setGCEventId(string $gcEventId): void
    {
        $this->gcEventId = $gcEventId;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param string $status
     */
    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    /**
     * @return \DateTime
     */
    public function getStart(): \DateTime
    {
        return $this->start;
    }

    /**
     * @param \DateTime $start
     */
    public function setStart(\DateTime $start): void
    {
        $this->start = $start;
    }

    /**
     * @return \DateTime
     */
    public function getEnd(): \DateTime
    {
        return $this->end;
    }

    /**
     * @param \DateTime $end
     */
    public function setEnd(\DateTime $end): void
    {
        $this->end = $end;
    }

    /**
     * @return string
     */
    public function getPrenom(): string
    {
        return $this->prenom;
    }

    /**
     * @param string $prenom
     */
    public function setPrenom(string $prenom): void
    {
        $this->prenom = $prenom;
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
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @param string $email
     */
    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    /**
     * @return string
     */
    public function getPhone(): string
    {
        return $this->phone;
    }

    /**
     * @param string $phone
     */
    public function setPhone(string $phone): void
    {
        $this->phone = $phone;
    }

    /**
     * @return string
     */
    public function getUnite(): string
    {
        return $this->unite;
    }

    /**
     * @param string $unite
     */
    public function setUnite(string $unite): void
    {
        $this->unite = $unite;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $description
     */
    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    /**
     * @return Cabane
     */
    public function getCabane(): Cabane
    {
        return $this->cabane;
    }

    /**
     * @param Cabane $cabane
     */
    public function setCabane(Cabane $cabane): void
    {
        $this->cabane = $cabane;
    }

    /**
     * @return Reservation[]
     */
    public function getConflicts() {
        $reservations = $this->getCabane()->getReservations();
        $conflicts = [];
        foreach($reservations as $reservation) {
            if (($this->start < $reservation->start && $this->end > $reservation->start)
                || ($this->start < $reservation->end && $this->end > $reservation->end)) {
                $conflicts[] = $reservation;
            }
        }

        return $conflicts;
    }

    /**
     * @return string
     */
    public function getRefusedMotif(): string
    {
        return $this->refusedMotif;
    }

    /**
     * @param string $refusedMotif
     */
    public function setRefusedMotif(string $refusedMotif): void
    {
        $this->refusedMotif = $refusedMotif;
    }
}