<?php

namespace Ovesco\FacturationBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use NetBS\CoreBundle\Entity\StoredRule;
use NetBS\CoreBundle\Rule\Rule;

#[ORM\Table(name: 'ovesco_facturation_facture_models')]
#[ORM\Entity]
class FactureModel
{
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
    #[ORM\Column(name: 'name', type: 'text', length: 255)]
    protected $name;

    /**
     * @var string
     */
    #[ORM\Column(name: 'application_rule', type: 'text', length: 255, nullable: true)]
    protected $applicationRule;

    /**
     * « S'applique quand » rule. Until one is set, the legacy applicationRule expression applies.
     */
    #[ORM\OneToOne(targetEntity: StoredRule::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\JoinColumn(name: 'rule_id', nullable: true, onDelete: 'SET NULL')]
    protected ?StoredRule $rule = null;

    const TEXTES_ANCIEN  = 1;
    const TEXTES_NOUVEAU = 2;

    /**
     * Format of titre / topDescription / bottomSalutations: TEXTES_ANCIEN ([expression] parsed the
     * old way) until the model is saved from the new editor; new models use TEXTES_NOUVEAU.
     */
    #[ORM\Column(name: 'textes_format', type: 'smallint', options: ['default' => 1])]
    protected int $textesFormat = self::TEXTES_ANCIEN;

    /**
     * @var string
     */
    #[ORM\Column(name: 'top_description', type: 'text')]
    protected $topDescription;

    /**
     * @var string
     */
    #[ORM\Column(name: 'titre', type: 'text')]
    protected $titre;

    /**
     * @var string
     */
    #[ORM\Column(name: 'bottom_salutations', type: 'text')]
    protected $bottomSalutations;

    /**
     * @var string
     */
    #[ORM\Column(name: 'signataire', type: 'string', length: 255)]
    protected $signataire;

    /**
     * @var string
     */
    #[ORM\Column(name: 'group_name', type: 'string', length: 255)]
    protected $groupName;

    /**
     * @var string
     */
    #[ORM\Column(name: 'rue', type: 'string', length: 255)]
    protected $rue;

    /**
     * @var string
     */
    #[ORM\Column(name: 'npa_ville', type: 'string', length: 255)]
    protected $npaVille;

    /**
     * @var string
     */
    #[ORM\Column(name: 'city_from', type: 'string', length: 255)]
    protected $cityFrom;

    /**
     * @var int
     */
    #[ORM\Column(name: 'poids', type: 'integer')]
    protected $poids;

    public function __construct()
    {
        $this->textesFormat = self::TEXTES_NOUVEAU;
    }

    public function getTextesFormat(): int
    {
        return $this->textesFormat;
    }

    public function setTextesFormat(int $textesFormat): void
    {
        $this->textesFormat = $textesFormat;
    }

    public function __toString() {

        return $this->name;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getApplicationRule()
    {
        return $this->applicationRule;
    }

    /**
     * @param string $applicationRule
     */
    public function setApplicationRule($applicationRule)
    {
        $this->applicationRule = $applicationRule;
    }

    public function getRule(): ?StoredRule
    {
        return $this->rule;
    }

    public function setRule(?StoredRule $rule): void
    {
        $this->rule = $rule;
    }

    /**
     * The rule as the form edits it: the rule row's JSON, else the legacy expression as an
     * expert rule, else null (no condition).
     */
    public function getRuleJson(): ?string
    {
        if ($this->rule !== null) {
            return $this->rule->getDefinition();
        }

        return trim((string) $this->applicationRule) !== '' ? Rule::expert($this->applicationRule)->toJson() : null;
    }

    /**
     * Unchanged: nothing happens, so an untouched legacy expression stays as it is. Otherwise the
     * rule row is created, updated or (empty rule) removed, and the legacy expression cleared.
     */
    public function setRuleJson(?string $json): void
    {
        if ($json === $this->getRuleJson()) {
            return;
        }

        $this->applicationRule = null;
        $this->rule            = StoredRule::assign($this->rule, 'facture', $json);
    }

    /**
     * @return string
     */
    public function getTopDescription()
    {
        return $this->topDescription;
    }

    /**
     * @param string $topDescription
     */
    public function setTopDescription($topDescription)
    {
        $this->topDescription = $topDescription;
    }

    /**
     * @return string
     */
    public function getBottomSalutations()
    {
        return $this->bottomSalutations;
    }

    /**
     * @param string $bottomSalutations
     */
    public function setBottomSalutations($bottomSalutations)
    {
        $this->bottomSalutations = $bottomSalutations;
    }

    /**
     * @return string
     */
    public function getSignataire()
    {
        return $this->signataire;
    }

    /**
     * @param string $signataire
     */
    public function setSignataire($signataire)
    {
        $this->signataire = $signataire;
    }

    /**
     * @return string
     */
    public function getGroupName()
    {
        return $this->groupName;
    }

    /**
     * @param string $groupName
     */
    public function setGroupName($groupName)
    {
        $this->groupName = $groupName;
    }

    /**
     * @return string
     */
    public function getRue()
    {
        return $this->rue;
    }

    /**
     * @param string $rue
     */
    public function setRue($rue)
    {
        $this->rue = $rue;
    }

    /**
     * @return string
     */
    public function getNpaVille()
    {
        return $this->npaVille;
    }

    /**
     * @param string $npaVille
     */
    public function setNpaVille($npaVille)
    {
        $this->npaVille = $npaVille;
    }

    /**
     * @return string
     */
    public function getCityFrom()
    {
        return $this->cityFrom;
    }

    /**
     * @param string $cityFrom
     */
    public function setCityFrom($cityFrom)
    {
        $this->cityFrom = $cityFrom;
    }

    /**
     * @return string
     */
    public function getTitre()
    {
        return $this->titre;
    }

    /**
     * @param string $titre
     */
    public function setTitre($titre)
    {
        $this->titre = $titre;
    }

    /**
     * @return int
     */
    public function getPoids()
    {
        return $this->poids;
    }

    /**
     * @param int $poids
     */
    public function setPoids($poids)
    {
        $this->poids = intval($poids);
    }
}