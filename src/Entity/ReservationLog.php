<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Table(name="sauvabelin_apmbs_reservation_logs")
 * @ORM\Entity()
 */
class ReservationLog {

    const MODIFY = 'modification';
    const MODIFICATION_ACCEPTED  = 'modification_acceptation';
    const ACCEPTED = 'validation';
    const REFUSED = 'refus';
    const CANCELLED = 'annulation';

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var APMBSReservation
     * @ORM\ManyToOne(targetEntity="App\Entity\APMBSReservation", inversedBy="logs")
     */
    protected $reservation;

    /**
     * @var \DateTime
     * @ORM\Column(name="created_at", type="datetime", options={"default": "CURRENT_TIMESTAMP"})
     */
    protected $createdAt;

    /**
     * @var string
     *
     * @ORM\Column(name="action", type="string", length=255)
     */
    protected $action;

    /**
     * @var string
     * @ORM\Column(name="payload", type="text", nullable=true)
     */
    protected $payload;

    /**
     * @var string
     * @ORM\Column(name="comment", type="text", nullable=true)
     */
    protected $comment;

    /**
     * @var string
     * @ORM\Column(name="username", type="string", length=255)
     */
    protected $username;

    public function __construct() {
        $this->createdAt = new \DateTime();
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
    public function getAction(): string
    {
        return $this->action;
    }

    /**
     * @param string $status
     */
    public function setAction(string $status): void
    {
        $this->action = $status;
    }

    public function getComment() {
        return $this->comment;
    }

    public function setComment($comment) {
        $this->comment = $comment;
    }

    public function getPayload() {
        return $this->payload;
    }

    public function setPayload($payload) {
        $this->payload = json_encode($payload, JSON_PRETTY_PRINT);
    }

    public function getReservation() {
        return $this->reservation;
    }

    public function setReservation($reservationId) {
        $this->reservation = $reservationId;
    }

    public function getUsername() {
        return $this->username;
    }

    public function setUsername($username) {
        $this->username = $username;
    }
}