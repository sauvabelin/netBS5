<?php

namespace Iacopo\MailingBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Iacopo\MailingBundle\Validator\UniqueMailingAddress;

/**
 * @UniqueEntity(
 *     fields={"baseAddress"},
 *     message="Cette adresse de base est déjà utilisée."
 * )
 * @UniqueMailingAddress
 */
#[ORM\Entity(repositoryClass: \Iacopo\MailingBundle\Repository\MailingListRepository::class)]
#[ORM\Table(name: 'mailing_list')]
class MailingList
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private $id;

    /**
     * @Assert\NotBlank(message="Le nom est requis.")
     * @Assert\Length(
     *     max=255,
     *     maxMessage="Le nom ne peut pas dépasser {{ limit }} caractères."
     * )
     */
    #[ORM\Column(type: 'string', length: 255)]
    private $name;

    /**
     * @Assert\NotBlank(message="L'adresse de base est requise.")
     * @Assert\Email(message="L'adresse de base doit être une adresse email valide.")
     * @Assert\Length(
     *     max=255,
     *     maxMessage="L'adresse de base ne peut pas dépasser {{ limit }} caractères."
     * )
     */
    #[ORM\Column(type: 'string', length: 255, unique: true)]
    private $baseAddress;

    /**
     * @Assert\Length(
     *     max=2000,
     *     maxMessage="La description ne peut pas dépasser {{ limit }} caractères."
     * )
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private $description;

    #[ORM\OneToMany(targetEntity: MailingTarget::class, mappedBy: 'mailingList', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private $targets;

    #[ORM\OneToMany(targetEntity: MailingListAlias::class, mappedBy: 'mailingList', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private $aliases;

    #[ORM\Column(type: 'datetime')]
    private $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private $updatedAt;

    #[ORM\Column(type: 'boolean')]
    private $active = true;

    public function __construct()
    {
        $this->targets = new ArrayCollection();
        $this->aliases = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getBaseAddress(): ?string
    {
        return $this->baseAddress;
    }

    public function setBaseAddress(string $baseAddress): self
    {
        $this->baseAddress = $baseAddress;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return Collection|MailingTarget[]
     */
    public function getTargets(): Collection
    {
        return $this->targets;
    }

    public function addTarget(MailingTarget $target): self
    {
        if (!$this->targets->contains($target)) {
            $this->targets[] = $target;
            $target->setMailingList($this);
        }
        return $this;
    }

    public function removeTarget(MailingTarget $target): self
    {
        if ($this->targets->removeElement($target)) {
            if ($target->getMailingList() === $this) {
                $target->setMailingList(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection|MailingListAlias[]
     */
    public function getAliases(): Collection
    {
        return $this->aliases;
    }

    public function addAlias(MailingListAlias $alias): self
    {
        if (!$this->aliases->contains($alias)) {
            $this->aliases[] = $alias;
            $alias->setMailingList($this);
        }
        return $this;
    }

    public function removeAlias(MailingListAlias $alias): self
    {
        if ($this->aliases->removeElement($alias)) {
            if ($alias->getMailingList() === $this) {
                $alias->setMailingList(null);
            }
        }
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

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): self
    {
        $this->active = $active;
        return $this;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
