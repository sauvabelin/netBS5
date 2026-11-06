<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use NetBS\CoreBundle\Entity\StoredRule;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * An LDAP group that maps to no groupe: its members are the membres (with a
 * directory user) matching its rule (context membre).
 */
#[ORM\Table(name: 'netbs_auth_directory_computed_groups')]
#[ORM\Entity]
class DirectoryComputedGroup
{
    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    /** The lldap group name, made DN-safe when projected. */
    #[ORM\Column(name: 'name', type: 'string', length: 255, unique: true)]
    #[Assert\NotBlank]
    private string $name = '';

    #[ORM\Column(name: 'description', type: 'string', length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\OneToOne(targetEntity: StoredRule::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\JoinColumn(name: 'membres_rule_id', nullable: false)]
    private ?StoredRule $membres = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = trim((string) $name);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description === null || trim($description) === '' ? null : trim($description);

        return $this;
    }

    public function getMembres(): ?StoredRule
    {
        return $this->membres;
    }

    public function getMembresJson(): ?string
    {
        return $this->membres?->getDefinition();
    }

    /** An empty rule leaves no row; validation reports it. */
    public function setMembresJson(?string $json): void
    {
        $this->membres = StoredRule::assign($this->membres, 'membre', $json);
    }

    #[Assert\Callback]
    public function validateRule(ExecutionContextInterface $context): void
    {
        if ($this->membres === null) {
            $context->buildViolation('Choisissez au moins une condition.')
                ->atPath('membresJson')
                ->addViolation();
        }
    }
}
