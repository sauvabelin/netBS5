<?php

namespace NetBS\FichierBundle\Mapping;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use NetBS\FichierBundle\Model\AdressableInterface;
use NetBS\FichierBundle\Model\EmailableInterface;
use NetBS\FichierBundle\Model\StatuableInterface;
use NetBS\FichierBundle\Model\TelephonableInterface;
use NetBS\FichierBundle\Utils\Entity\ContactTrait;
use NetBS\FichierBundle\Utils\Entity\RemarqueTrait;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Personne
 */
abstract class Personne implements AdressableInterface, TelephonableInterface, EmailableInterface, StatuableInterface
{
    use ContactTrait, RemarqueTrait, TimestampableEntity;

    const   HOMME   = 'homme';
    const   FEMME   = 'femme';

    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     * @Groups({"default"})
     */
    protected $id;

    /**
     * @var string
     * @Assert\NotBlank()
     * @ORM\Column(name="prenom", type="string", length=255)
     * @Groups({"default"})
     */
    protected $prenom;

    /**
     * @var string
     *
     * @ORM\Column(name="sexe", type="string", length=255)
     * @Groups({"default"})
     */
    protected $sexe;

    /**
     * @var BaseContactInformation
     * @Assert\Valid()
     */
    protected $contactInformation;

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
     * Set prenom
     *
     * @param string $prenom
     *
     * @return Personne
     */
    public function setPrenom($prenom)
    {
        $this->prenom = $prenom;

        return $this;
    }

    /**
     * Get prenom
     *
     * @return string
     */
    public function getPrenom()
    {
        return $this->prenom;
    }

    /**
     * Set sexe
     *
     * @param string $sexe
     *
     * @return Personne
     */
    public function setSexe($sexe)
    {
        $this->sexe = $sexe;

        return $this;
    }

    /**
     * Get sexe
     *
     * @return string
     */
    public function getSexe()
    {
        return $this->sexe;
    }
}

