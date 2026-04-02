<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Table(name: 'apmbs_intendant')]
#[ORM\Entity]
class Intendant {

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
     * @var string
     */
    #[ORM\Column(name: 'email', type: 'string', length: 255, nullable: true)]
    protected $email;

    /**
     * @var string
     */
    #[ORM\Column(name: 'phone', type: 'string', length: 255, nullable: true)]
    protected $phone;

    /**
     * @var BSUser
     */
    #[ORM\ManyToOne(targetEntity: BSUser::class)]
    protected $user;

    /**
     * @var ArrayCollection
     */
    #[ORM\ManyToMany(targetEntity: Cabane::class, mappedBy: 'intendants')]
    protected $cabanes;

    /**
     * @var ArrayCollection
     */
    #[ORM\ManyToMany(targetEntity: APMBSReservation::class, mappedBy: 'intendant')]
    protected $reservations;

    public function __construct() {
        $this->cabanes = new ArrayCollection();
    }

    public function __toString()
    {
        return $this->nom;
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

    public function getUser() {
        return $this->user;
    }

    public function setUser($user): void {
        $this->user = $user;
    }

    public function getCabanes() {
        return $this->cabanes;
    }

    public function setCabanes($cabanes): void {
        $this->cabanes = $cabanes;
    }

    public function addCabane(Cabane $cabane) {
        $this->cabanes[] = $cabane;
    }

    public function removeCabane(Cabane $cabane) {
        $this->cabanes->removeElement($cabane);
    }

    public function getEmail() {
        return $this->email;
    }

    public function setEmail($email) {
        $this->email = $email;
    }

    public function getPhone() {
        return $this->phone;
    }

    public function setPhone($phone) {
        $this->phone = $phone;
    }
}