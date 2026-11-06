<?php

namespace NetBS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use NetBS\CoreBundle\Repository\StoredRuleRepository;
use NetBS\CoreBundle\Rule\InvalidRuleException;
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

    /** rule(), parsed once: a sync evaluates the same rule for every groupe / membre. */
    private ?Rule $parsed = null;

    public function __construct(string $context, string $definition, ?string $code = null)
    {
        $this->context    = $context;
        $this->definition = $definition;
        $this->code       = $code;
    }

    /**
     * The owner's rule row after setting $json on it: updated in place, created, or (no rule, empty
     * rule) null. Malformed JSON is kept, so the owner's validation reports it rather than silently
     * dropping it. The one place owners turn rule JSON into a row.
     */
    public static function assign(?self $row, string $context, ?string $json): ?self
    {
        if (self::isEmptyJson($json)) {
            return null;
        }
        if ($row === null) {
            return new self($context, $json);
        }

        return $row->setDefinition($json);
    }

    /** No rule, or one without any condition. Malformed JSON is not empty. */
    public static function isEmptyJson(?string $json): bool
    {
        if ($json === null) {
            return true;
        }
        try {
            return Rule::fromJson($json)->isEmpty();
        } catch (InvalidRuleException) {
            return false;
        }
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
        $this->parsed     = null;

        return $this;
    }

    public function rule(): Rule
    {
        return $this->parsed ??= Rule::fromJson($this->definition);
    }

    public function copy(): self
    {
        return new self($this->context, $this->definition);
    }
}
