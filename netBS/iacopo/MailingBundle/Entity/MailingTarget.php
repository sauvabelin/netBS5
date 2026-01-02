<?php

namespace Iacopo\MailingBundle\Entity;

use App\Entity\BSUser;
use App\Entity\BSGroupe;
use NetBS\FichierBundle\Entity\Fonction;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Iacopo\MailingBundle\Validator\ValidMailingTarget;

/**
 * @ORM\Entity(repositoryClass="Iacopo\MailingBundle\Repository\MailingTargetRepository")
 * @ORM\Table(name="mailing_target")
 * @ValidMailingTarget
 */
class MailingTarget
{
    const TYPE_EMAIL = 'email';
    const TYPE_USER = 'user';
    const TYPE_UNITE = 'unite';
    const TYPE_ROLE = 'role';
    const TYPE_LIST = 'list';

    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;

    /**
     * @ORM\ManyToOne(targetEntity="MailingList", inversedBy="targets")
     * @ORM\JoinColumn(nullable=false, onDelete="CASCADE")
     * @Assert\NotNull(message="La liste de diffusion est requise.")
     */
    private $mailingList;

    /**
     * @ORM\Column(type="string", length=50)
     * @Assert\NotBlank(message="Le type est requis.")
     * @Assert\Choice(
     *     choices={"email", "user", "unite", "role", "list"},
     *     message="Type invalide."
     * )
     */
    private $type;

    /**
     * @ORM\Column(type="string", length=255, nullable=true)
     * @Assert\Email(
     *     message="L'adresse email n'est pas valide.",
     *     groups={"email_type"}
     * )
     * @Assert\Length(
     *     max=255,
     *     maxMessage="L'adresse email ne peut pas dépasser {{ limit }} caractères."
     * )
     */
    private $targetEmail;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\BSUser")
     * @ORM\JoinColumn(nullable=true, onDelete="CASCADE")
     */
    private $targetUser;

    /**
     * @ORM\ManyToOne(targetEntity="App\Entity\BSGroupe")
     * @ORM\JoinColumn(nullable=true, onDelete="CASCADE")
     */
    private $targetGroup;

    /**
     * @ORM\ManyToOne(targetEntity="NetBS\FichierBundle\Entity\Fonction")
     * @ORM\JoinColumn(nullable=true, onDelete="CASCADE")
     */
    private $targetFonction;

    /**
     * @ORM\ManyToOne(targetEntity="Iacopo\MailingBundle\Entity\MailingList")
     * @ORM\JoinColumn(nullable=true, onDelete="CASCADE")
     */
    private $targetList;

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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        if (!in_array($type, [self::TYPE_EMAIL, self::TYPE_USER, self::TYPE_UNITE, self::TYPE_ROLE, self::TYPE_LIST])) {
            throw new \InvalidArgumentException("Invalid target type");
        }
        $this->type = $type;
        return $this;
    }

    public function getTargetEmail(): ?string
    {
        return $this->targetEmail;
    }

    public function setTargetEmail(?string $targetEmail): self
    {
        $this->targetEmail = $targetEmail;
        return $this;
    }

    public function getTargetUser(): ?BSUser
    {
        return $this->targetUser;
    }

    public function setTargetUser(?BSUser $targetUser): self
    {
        $this->targetUser = $targetUser;
        return $this;
    }

    public function getTargetGroup(): ?BSGroupe
    {
        return $this->targetGroup;
    }

    public function setTargetGroup(?BSGroupe $targetGroup): self
    {
        $this->targetGroup = $targetGroup;
        return $this;
    }

    public function getTargetFonction(): ?Fonction
    {
        return $this->targetFonction;
    }

    public function setTargetFonction(?Fonction $targetFonction): self
    {
        $this->targetFonction = $targetFonction;
        return $this;
    }

    public function getTargetList(): ?MailingList
    {
        return $this->targetList;
    }

    public function setTargetList(?MailingList $targetList): self
    {
        $this->targetList = $targetList;
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

    /**
     * Get the display value for this target
     */
    public function getDisplayValue(): string
    {
        switch ($this->type) {
            case self::TYPE_EMAIL:
                return $this->targetEmail ?? '';
            case self::TYPE_USER:
                return $this->targetUser ? $this->targetUser->getUsername() : '';
            case self::TYPE_UNITE:
                return $this->targetGroup ? $this->targetGroup->getNom() : '';
            case self::TYPE_ROLE:
                return $this->targetFonction ? $this->targetFonction->getNom() : '';
            case self::TYPE_LIST:
                return $this->targetList ? $this->targetList->getName() : '';
            default:
                return '';
        }
    }

    public function __toString(): string
    {
        return $this->getDisplayValue();
    }
}
