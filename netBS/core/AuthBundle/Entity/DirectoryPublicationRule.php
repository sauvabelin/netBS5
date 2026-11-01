<?php

declare(strict_types=1);

namespace NetBS\AuthBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use NetBS\AuthBundle\Repository\DirectoryPublicationRuleRepository;
use NetBS\CoreBundle\Entity\StoredRule;
use NetBS\CoreBundle\Rule\Rule;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Publishes the groupes its selection rule (context groupe) matches as LDAP
 * groups. Rules are tried by position; each groupe follows the first rule
 * that selects it. Members: the groupe's own attributions, plus its
 * sub-groupes' ones according to the sous-groupes mode.
 */
#[ORM\Table(name: 'netbs_auth_directory_publication_rules')]
#[ORM\Entity(repositoryClass: DirectoryPublicationRuleRepository::class)]
class DirectoryPublicationRule
{
    /** Only the groupe's own attributions */
    public const SOUS_GROUPES_DIRECT = 'direct';

    /** ...plus every sub-groupe attribution */
    public const SOUS_GROUPES_TOUS = 'tous';

    /** ...plus the sub-groupe attributions matching sousGroupesRule (context attribution_sous_groupe) */
    public const SOUS_GROUPES_REGLE = 'regle';

    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    private ?int $id = null;

    #[ORM\Column(name: 'position', type: 'integer')]
    private int $position = 0;

    #[ORM\Column(name: 'label', type: 'string', length: 255)]
    #[Assert\NotBlank]
    private string $label = '';

    #[ORM\OneToOne(targetEntity: StoredRule::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\JoinColumn(name: 'selection_rule_id', nullable: false)]
    private ?StoredRule $selection = null;

    #[ORM\Column(name: 'sous_groupes', type: 'string', length: 16)]
    private string $sousGroupes = self::SOUS_GROUPES_TOUS;

    #[ORM\OneToOne(targetEntity: StoredRule::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\JoinColumn(name: 'sous_groupes_rule_id', nullable: true, onDelete: 'SET NULL')]
    private ?StoredRule $sousGroupesRule = null;

    /** @return array<string, string> label => mode */
    public static function getSousGroupesChoices(): array
    {
        return [
            'Membres du groupe seulement'                   => self::SOUS_GROUPES_DIRECT,
            'Avec les membres de tous ses sous-groupes'     => self::SOUS_GROUPES_TOUS,
            'Avec les membres des sous-groupes selon une règle' => self::SOUS_GROUPES_REGLE,
        ];
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = (string) $label;

        return $this;
    }

    public function getSelection(): ?StoredRule
    {
        return $this->selection;
    }

    public function getSelectionJson(): ?string
    {
        return $this->selection?->getDefinition();
    }

    /** An empty rule is kept, so validation reports it. */
    public function setSelectionJson(?string $json): void
    {
        $this->selection = StoredRules::update($this->selection, 'groupe', $json ?? Rule::empty()->toJson(), true);
    }

    public function getSousGroupes(): string
    {
        return $this->sousGroupes;
    }

    public function setSousGroupes(string $sousGroupes): self
    {
        if (!in_array($sousGroupes, self::getSousGroupesChoices(), true)) {
            throw new \InvalidArgumentException(sprintf('Unknown sous-groupes mode "%s".', $sousGroupes));
        }
        $this->sousGroupes = $sousGroupes;

        return $this;
    }

    public function getSousGroupesRule(): ?StoredRule
    {
        return $this->sousGroupesRule;
    }

    public function getSousGroupesRuleJson(): ?string
    {
        return $this->sousGroupesRule?->getDefinition();
    }

    /** Creates, updates or (empty rule) removes the rule row. */
    public function setSousGroupesRuleJson(?string $json): void
    {
        $this->sousGroupesRule = StoredRules::update($this->sousGroupesRule, 'attribution_sous_groupe', $json, false);
    }

    #[Assert\Callback]
    public function validateRules(ExecutionContextInterface $context): void
    {
        if (StoredRules::isEmpty($this->selection)) {
            $context->buildViolation('Choisissez au moins une condition.')
                ->atPath('selectionJson')
                ->addViolation();
        }
        if ($this->sousGroupes === self::SOUS_GROUPES_REGLE && StoredRules::isEmpty($this->sousGroupesRule)) {
            $context->buildViolation('Choisissez au moins une condition pour le mode « selon une règle ».')
                ->atPath('sousGroupesRuleJson')
                ->addViolation();
        }
    }
}
