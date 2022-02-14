<?php

namespace NetBS\FichierBundle\Mapping;

use Doctrine\ORM\Mapping as ORM;
use NetBS\FichierBundle\Utils\Entity\ExpediableTrait;
use NetBS\FichierBundle\Utils\Entity\RemarqueTrait;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Email
 * @ORM\MappedSuperclass()
 */
class BaseEmail
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
     * @Assert\Email
     * @Assert\NotBlank()
     * @ORM\Column(name="email", type="string", length=255)
     * @Groups({"default"})
     */
    protected $email;

    /**
     * @var BaseContactInformation
     */
    protected $contactInformation;

    public function __construct($email = null)
    {
        $this->setEmail($email);
    }

    public function __toString()
    {
        return $this->getEmail();
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
     * Set email
     *
     * @param string $email
     *
     * @return BaseEmail
     */
    public function setEmail($email)
    {
        $this->email = $email;

        return $this;
    }

    /**
     * Get email
     *
     * @return string
     */
    public function getEmail()
    {
        return $this->email;
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
     * @return self
     */
    public function setContactInformation($contactInformation)
    {
        $this->contactInformation = $contactInformation;
        return $this;
    }
}

