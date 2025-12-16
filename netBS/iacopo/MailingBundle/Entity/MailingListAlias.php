<?php

namespace Iacopo\MailingBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Iacopo\MailingBundle\Validator\UniqueMailingAddress;

/**
 * @ORM\Entity(repositoryClass="Iacopo\MailingBundle\Repository\MailingListAliasRepository")
 * @ORM\Table(name="mailing_list_alias")
 * @UniqueMailingAddress
 */
class MailingListAlias
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="MailingList", inversedBy="aliases")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     * @Assert\NotNull(message="La liste de diffusion est requise.")
     */
    private $mailingList;

    /**
     * @ORM\Column(type="string", length=255)
     * @Assert\NotBlank(message="L'adresse est requise.")
     * @Assert\Email(message="L'adresse doit être une adresse email valide.")
     * @Assert\Length(
     *     max=255,
     *     maxMessage="L'adresse ne peut pas dépasser {{ limit }} caractères."
     * )
     */
    private $address;

    /**
     * @ORM\Column(type="datetime")
     */
    private $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMailingList(): ?MailingList
    {
        return $this->mailingList;
    }

    public function setMailingList(?MailingList $mailingList): self
    {
        $this->mailingList = $mailingList;
        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): self
    {
        $this->address = $address;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function __toString(): string
    {
        return $this->address ?? '';
    }
}
