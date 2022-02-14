<?php

namespace NetBS\FichierBundle\Mapping;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use NetBS\FichierBundle\Utils\Entity\ExpediableTrait;
use NetBS\FichierBundle\Utils\Entity\RemarqueTrait;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;


/**
 * Telephone
 *
 * @ORM\MappedSuperclass()
 */
class BaseTelephone
{
    use TimestampableEntity, RemarqueTrait, ExpediableTrait;

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
     * @ORM\Column(name="telephone", type="string", length=255)
     * @Groups({"default"})
     * @Assert\NotBlank()
     */
    protected $telephone;

    /**
     * @var BaseContactInformation
     */
    protected $contactInformation;

    public function __construct($telephone = null)
    {
        $this->setTelephone($telephone);
    }

    public function __toString()
    {
        return $this->getTelephone();
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
     * Set telephone
     *
     * @param string $telephone
     *
     * @return BaseTelephone
     */
    public function setTelephone($telephone)
    {
        $this->telephone = $telephone;

        return $this;
    }

    /**
     * Get telephone
     *
     * @return string
     */
    public function getTelephone()
    {
        $telephone = preg_replace("/[^0-9]/", "", $this->telephone);
        if(strlen($telephone) == 10)
            $telephone =
                $telephone[0] .
                $telephone[1] .
                $telephone[2] . " " .
                $telephone[3] .
                $telephone[4] .
                $telephone[5] . " " .
                $telephone[6] .
                $telephone[7] . " " .
                $telephone[8] .
                $telephone[9];

        return $telephone;
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
}

