<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;


/**
 * @ORM\Table(name="sauvabelin_apmbs_reservations")
 * @ORM\Entity()
 */
class APMBSReservation {

    const PENDING = '1_pending';
    const MODIFICATION_PENDING = '2_modification_pending';
    const MODIFICATION_ACCEPTED = '3_modification_accepted';
    const ACCEPTED = '4_accepted';
    const REFUSED = '5_refused';
    const CANCELLED = '6_cancelled';
    const INVOICE_SENT = '7_invoice_sent';
    const CLOSED = '8_closed';

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
     * @var \DateTime
     * @ORM\Column(name="created_at", type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    protected $createdAt;

    /**
     * @var Cabane
     *
     * @ORM\ManyToOne(targetEntity="App\Entity\Cabane", inversedBy="reservations")
     */
    protected $cabane;

    /**
     * @var ReservationLog[]
     * @ORM\OneToMany(targetEntity="App\Entity\ReservationLog", mappedBy="reservation")
     */
    protected $logs;

    /**
     * @var string
     *
     * @ORM\Column(name="status", type="string", length=255)
     */
    protected $status;

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
     * @Assert\NotBlank()
     * @ORM\Column(name="rue", type="string", length=255)
     */
    protected $rue;

    /**
     * @var string
     * @Assert\NotBlank()
     * @ORM\Column(name="npa", type="string", length=255)
     */
    protected $npa;

    /**
     * @var string
     * @Assert\NotBlank()
     * @ORM\Column(name="localite", type="string", length=255)
     */
    protected $localite;

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
     * @ORM\Column(name="description", type="text", nullable=true)
     */
    protected $description;

    /**
     * @var string
     * @ORM\Column(name="comment", type="text", nullable=true)
     */
    protected $comment;

    /**
     * @var ArrayCollection
     * @ORM\ManyToOne(targetEntity="App\Entity\Intendant")
     */
    protected $intendantDebut;

    /**
     * @var ArrayCollection
     * @ORM\ManyToOne(targetEntity="App\Entity\Intendant")
     */
    protected $intendantFin;

    /**
     * @var bool
     * @ORM\Column(name="block_start_day", type="boolean")
     */
    protected $blockStartDay = true;

    /**
     * @var bool
     * @ORM\Column(name="block_end_day", type="boolean")
     */
    protected $blockEndDay = true;

    /**
     * @var float
     * @ORM\Column(name="estimated_price", type="float")
     */
    protected $estimatedPrice = 0;

    /**
     * @var float
     * @ORM\Column(name="final_price", type="float")
     */
    protected $finalPrice = 0;
    
    public function __construct() {
        $this->createdAt = new \DateTime();
        $this->status = self::PENDING;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
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
    public function setGCEventId($gcEventId): void
    {
        $this->gcEventId = $gcEventId;
    }

    /**
     * @return string
     */
    public function getStatus(): string
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

    public function getComment() {
        return $this->comment;
    }

    public function setComment($comment) {
        $this->comment = $comment;
    }

    public function addLog(ReservationLog $log) {
        $this->logs[] = $log;
    }

    public function getLogs() {
        return $this->logs;
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

    public function getTitle() {
        $summary = "";
        $summary .= $this->getPrenom() . " " . $this->getNom() . " - ";
        $summary .= $this->getUnite();

        return $summary;
    }

    public function toJSON() {
        return [
            'start' => $this->getStart()->format('Y-m-d H:i:s'),
            'end'   => $this->getEnd()->format('Y-m-d H:i:s'),
            'status'=> $this->getStatus(),
            'blockStartDay' => $this->getBlockStartDay(),
            'blockEndDay' => $this->getBlockEndDay(),
        ];
    }

    public function getIntendantDebut() {
        return $this->intendantDebut;
    }

    public function setIntendantDebut($intendantDebut) {
        $this->intendantDebut = $intendantDebut;
    }

    public function getIntendantFin() {
        return $this->intendantFin;
    }

    public function setIntendantFin($intendantFin) {
        $this->intendantFin = $intendantFin;
    }

    public function getExpectedPrice() {
        $engine = new ExpressionLanguage();
        $context = [
            'start' => $this->start,
            'end'   => $this->end,
        ];

        if (!$this->getCabane()->getPriceMethod())
            return false;

        try {
            return $engine->evaluate($this->getCabane()->getPriceMethod(), $context);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function getBlockStartDay() {
        return $this->blockStartDay;
    }

    public function setBlockStartDay($blockStartDay) {
        $this->blockStartDay = $blockStartDay;
    }

    public function getBlockEndDay() {
        return $this->blockEndDay;
    }

    public function setBlockEndDay($blockEndDay) {
        $this->blockEndDay = $blockEndDay;
    }

    public function getEstimatedPrice() {
        return $this->estimatedPrice;
    }

    public function setEstimatedPrice($estimatedPrice) {
        $this->estimatedPrice = $estimatedPrice;
    }

    public function getFinalPrice() {
        return $this->finalPrice;
    }

    public function setFinalPrice($finalPrice) {
        $this->finalPrice = $finalPrice;
    }

    public function getRue() {
        return $this->rue;
    }

    public function setRue($rue) {
        $this->rue = $rue;
    }

    public function getNpa() {
        return $this->npa;
    }

    public function setNpa($npa) {
        $this->npa = $npa;
    }

    public function getLocalite() {
        return $this->localite;
    }

    public function setLocalite($localite) {
        $this->localite = $localite;
    }
}