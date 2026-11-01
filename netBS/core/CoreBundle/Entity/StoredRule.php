<?php

namespace NetBS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use NetBS\CoreBundle\Repository\StoredRuleRepository;
use NetBS\CoreBundle\Rule\Rule;

/**
 * A rule owned by exactly one object: a facture model or groupe type (foreign key on the owner),
 * or a setting (found by code = setting id).
 */
#[ORM\Table(name: 'netbs_core_rules')]
#[ORM\Entity(repositoryClass: StoredRuleRepository::class)]
class StoredRule
{
    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private $id;

    #[ORM\Column(name: 'context', type: 'string', length: 64)]
    private string $context;

    #[ORM\Column(name: 'definition', type: 'text')]
    private string $definition;

    #[ORM\Column(name: 'code', type: 'string', length: 128, nullable: true, unique: true)]
    private ?string $code;

    public function __construct(string $context, string $definition, ?string $code = null)
    {
        $this->context    = $context;
        $this->definition = $definition;
        $this->code       = $code;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContext(): string
    {
        return $this->context;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function getDefinition(): string
    {
        return $this->definition;
    }

    public function setDefinition(string $json): self
    {
        $this->definition = $json;

        return $this;
    }

    public function rule(): Rule
    {
        return Rule::fromJson($this->definition);
    }

    public function copy(): self
    {
        return new self($this->context, $this->definition);
    }
}
