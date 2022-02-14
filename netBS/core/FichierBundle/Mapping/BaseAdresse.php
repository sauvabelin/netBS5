<?php

namespace NetBS\FichierBundle\Mapping;

use Doctrine\ORM\Mapping as ORM;
use NetBS\CoreBundle\Model\EqualInterface;
use NetBS\CoreBundle\Utils\Countries;
use NetBS\FichierBundle\Utils\Entity\ExpediableTrait;
use NetBS\FichierBundle\Utils\Entity\RemarqueTrait;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\GroupSequenceProviderInterface;

/**
 * @ORM\MappedSuperclass()
 * @Assert\GroupSequenceProvider()
 */
class BaseAdresse implements GroupSequenceProviderInterface, EqualInterface
{
    use RemarqueTrait, ExpediableTrait;

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
     *
     * @ORM\Column(name="rue", type="string", length=255)
     * @Groups({"default"})
     * @Assert\NotBlank(groups={"checkable"})
     */
    protected $rue;

    /**
     * @var int
     *
     * @ORM\Column(name="npa", type="integer")
     * @Groups({"default"})
     * @Assert\Range(min=1000, max=99999, groups={"checkable"})
     * @Assert\NotBlank(groups={"checkable"})
     */
    protected $npa;

    /**
     * @var string
     *
     * @ORM\Column(name="localite", type="string", length=255)
     * @Groups({"default"})
     * @Assert\NotBlank(groups={"checkable"})
     */
    protected $localite;

    /**
     * @var string
     *
     * @ORM\Column(name="pays", type="string", length=255)
     * @Groups({"default"})
     * @Assert\NotBlank(groups={"checkable"})
     */
    protected $pays = 'CH';

    /**
     * @var BaseContactInformation
     */
    protected $contactInformation;

    public function __toString()
    {
        return $this->rue . "\n" . $this->npa . " " . $this->localite;
    }

    /**
     * @param BaseAdresse $adresse
     * @return bool
     */
    public function equals($adresse)
    {
        if($adresse === null)
            return false;

        return $adresse instanceof BaseAdresse && $this->id === $adresse->getId();
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
     * Set rue
     *
     * @param string $rue
     *
     * @return BaseAdresse
     */
    public function setRue($rue)
    {
        $this->rue = $rue;

        return $this;
    }

    /**
     * Get rue
     *
     * @return string
     */
    public function getRue()
    {
        return $this->rue;
    }

    /**
     * Set npa
     *
     * @param integer $npa
     *
     * @return BaseAdresse
     */
    public function setNpa($npa)
    {
        $this->npa = $npa;

        return $this;
    }

    /**
     * Get npa
     *
     * @return int
     */
    public function getNpa()
    {
        return $this->npa;
    }

    /**
     * Set localite
     *
     * @param string $localite
     *
     * @return BaseAdresse
     */
    public function setLocalite($localite)
    {
        $this->localite = $localite;

        return $this;
    }

    /**
     * Get localite
     *
     * @return string
     */
    public function getLocalite()
    {
        return $this->localite;
    }

    /**
     * @return bool
     */
    public function isEmpty() {

        return empty($this->id) && empty($this->rue);
    }

    /**
     * @return BaseContactInformation
     */
    public function getContactInformation()
    {
        return $this->contactInformation;
    }

    /**
     * @param BaseContactInformation $contactInformation
     */
    public function setContactInformation($contactInformation)
    {
        $this->contactInformation = $contactInformation;
    }

    /**
     * Returns which validation groups should be used for a certain state
     * of the object.
     *
     * @return array An array of validation groups
     */
    public function getGroupSequence()
    {
        return [
            $this->isEmpty() ? '' : 'checkable'
        ];
    }

    /**
     * @return string
     */
    public function getPays()
    {
        return $this->pays;
    }

    /**
     * @return string
     */
    public function getPaysFull() {

        return Countries::getName($this->pays);
    }

    /**
     * @param string $pays
     * @return BaseAdresse
     */
    public function setPays($pays)
    {
        $this->pays = $pays;
        return $this;
    }
}
