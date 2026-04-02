<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'apmbs_cabane_time_period')]
#[ORM\Entity]
class CabaneTimePeriod {

    /**
     * @var int
     */
    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    protected $id;

    /**
     * @var string
     */
    #[ORM\Column(name: 'nom', type: 'string', length: 255)]
    #[Assert\NotBlank]
    protected $nom;


    /**
     * @var \DateTime
     */
    #[ORM\Column(name: 'time_start', type: 'time')]
    #[Assert\NotBlank]
    protected $timeStart;

    /**
     * @var \DateTime
     */
    #[ORM\Column(name: 'time_end', type: 'time')]
    #[Assert\NotBlank]
    protected $timeEnd;

    public function getId() {
        return $this->id;
    }

    public function getNom() {
        return $this->nom;
    }

    public function __toString() {
        return $this->nom;
    }

    public function setNom($nom) {
        $this->nom = $nom;
        return $this;
    }

    public function getTimeStart() {
        return $this->timeStart;
    }

    public function setTimeStart($timeStart) {
        $this->timeStart = $timeStart;
        return $this;
    }

    public function getTimeEnd() {
        return $this->timeEnd;
    }

    public function setTimeEnd($timeEnd) {
        $this->timeEnd = $timeEnd;
        return $this;
    }
}