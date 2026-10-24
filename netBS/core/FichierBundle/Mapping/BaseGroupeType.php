<?php

namespace NetBS\FichierBundle\Mapping;

use Doctrine\ORM\Mapping as ORM;
use NetBS\FichierBundle\Utils\Entity\RemarqueTrait;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\MappedSuperclass]
class BaseGroupeType
{
    use RemarqueTrait;

    /** Sub-groupe expansion for directory groups: only direct attributions */
    const SOUS_GROUPES_DIRECT = 'direct';

    /** ...plus every sub-groupe attribution */
    const SOUS_GROUPES_TOUS = 'tous';

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
     * @var bool
     */
    #[ORM\Column(name: 'affichageEffectifs', type: 'boolean')]
    protected $affichageEffectifs;

    #[ORM\Column(name: 'cree_groupe', type: 'boolean', options: ['default' => false])]
    protected $creeGroupe = false;

    #[ORM\Column(name: 'sous_groupes', type: 'string', length: 16, options: ['default' => self::SOUS_GROUPES_TOUS])]
    protected $sousGroupes = self::SOUS_GROUPES_TOUS;

    /**
     * @var BaseGroupeCategorie
     */
    #[Assert\NotBlank]
    protected $groupeCategorie;

    public function __construct()
    {
        $this->affichageEffectifs   = false;
    }

    public function __toString()
    {
        return $this->nom;
    }

    /**
     * Get id
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set nom
     *
     * @param string $nom
     *
     * @return BaseGroupeType
     */
    public function setNom($nom)
    {
        $this->nom = $nom;

        return $this;
    }

    /**
     * Get nom
     *
     * @return string
     */
    public function getNom()
    {
        return $this->nom;
    }

    /**
     * Set affichageEffectifs
     *
     * @param boolean $affichageEffectifs
     *
     * @return BaseGroupeType
     */
    public function setAffichageEffectifs($affichageEffectifs)
    {
        $this->affichageEffectifs = $affichageEffectifs;

        return $this;
    }

    /**
     * Get affichageEffectifs
     *
     * @return bool
     */
    public function getAffichageEffectifs()
    {
        return $this->affichageEffectifs;
    }

    /**
     * Set groupeCategorie
     *
     * @param BaseGroupeCategorie $groupeCategorie
     * @return self
     */
    public static function getSousGroupesChoices()
    {
        return [
            'Directs seulement'     => self::SOUS_GROUPES_DIRECT,
            'Tous'                  => self::SOUS_GROUPES_TOUS,
        ];
    }

    public function setCreeGroupe($creeGroupe)
    {
        $this->creeGroupe = (bool) $creeGroupe;

        return $this;
    }

    public function isCreeGroupe()
    {
        return $this->creeGroupe;
    }

    public function setSousGroupes($sousGroupes)
    {
        if (!in_array($sousGroupes, self::getSousGroupesChoices(), true)) {
            throw new \InvalidArgumentException(sprintf('Unknown sous-groupes mode "%s".', $sousGroupes));
        }

        $this->sousGroupes = $sousGroupes;

        return $this;
    }

    public function getSousGroupes()
    {
        return $this->sousGroupes;
    }

    public function setGroupeCategorie(BaseGroupeCategorie $groupeCategorie)
    {
        $this->groupeCategorie = $groupeCategorie;
        return $this;
    }

    /**
     * Get groupeCategorie
     *
     * @return BaseGroupeCategorie $groupeCategorie
     */
    public function getGroupeCategorie()
    {
        return $this->groupeCategorie;
    }
}

