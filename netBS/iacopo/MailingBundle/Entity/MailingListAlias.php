<?php

namespace Iacopo\MailingBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Iacopo\MailingBundle\Validator\UniqueMailingAddress;

/**
 * @UniqueMailingAddress
 */
#[ORM\Entity(repositoryClass: \Iacopo\MailingBundle\Repository\MailingListAliasRepository::class)]
#[ORM\Table(name: 'mailing_list_alias')]
class MailingListAlias
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    /**
     * @Assert\NotNull(message="La liste de diffusion est requise.")
     */
    #[ORM\ManyToOne(targetEntity: MailingList::class, inversedBy: 'aliases')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private $mailingList;

    /**
     * @Assert\NotBlank(message="L'adresse est requise.")
     * @Assert\Email(message="L'adresse doit être une adresse email valide.")
     * @Assert\Length(
     *     max=255,
     *     maxMessage="L'adresse ne peut pas dépasser {{ limit }} caractères."
     * )
     */
    #[ORM\Column(type: 'string', length: 255)]
    private $address;

    #[ORM\Column(type: 'datetime')]
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
