<?php

namespace NetBS\FichierBundle\Mapping;

use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

/**
 * Class ContactInformation
 * @package FichierBundle\Entity
 * @ORM\MappedSuperclass()
 */
class BaseContactInformation
{
    /**
     * @var int
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var BaseEmail[]
     * @Assert\Valid()
     * @Groups({"emails"})
     */
    protected $emails;

    /**
     * @var BaseTelephone[]
     * @Assert\Valid()
     * @Groups({"telephones"})
     */
    protected $telephones;

    /**
     * @var BaseAdresse[]
     * @Assert\Valid()
     * @Groups({"adresses"})
     */
    protected $adresses;

    public function __construct()
    {
        $this->telephones   = new ArrayCollection();
        $this->emails       = new ArrayCollection();
        $this->adresses     = new ArrayCollection();
    }

    public function _linkItems() {

        foreach($this->adresses as $adresse)
            $adresse->setContactInformation($this);

        foreach($this->telephones as $telephone)
            $telephone->setContactInformation($this);

        foreach($this->emails as $email)
            $email->setContactInformation($this);
    }

    /**
     * Add email
     *
     * @param BaseEmail $email
     */
    public function addEmail(BaseEmail $email)
    {
        $email->setContactInformation($this);
        $this->emails[] = $email;
    }

    /**
     * Remove email
     *
     * @param BaseEmail $email
     */
    public function removeEmail(BaseEmail $email)
    {
        foreach($this->emails as $key => $item) {
            if($email->getEmail() === $item->getEmail()) {
                unset($this->emails[$key]);
                break;
            }
        }
    }

    /**
     * Get emails
     *
     * @return BaseEmail[] $emails
     */
    public function getEmails()
    {
        return $this->emails;
    }

    /**
     * Add telephone
     *
     * @param BaseTelephone $telephone
     */
    public function addTelephone(BaseTelephone $telephone)
    {
        $telephone->setContactInformation($this);
        $this->telephones[] = $telephone;
    }

    /**
     * Remove telephone
     *
     * @param BaseTelephone $telephone
     */
    public function removeTelephone(BaseTelephone $telephone)
    {
        foreach($this->telephones as $key => $item) {
            if($telephone->getTelephone() === $item->getTelephone()) {
                unset($this->telephones[$key]);
                break;
            }
        }
    }

    /**
     * Get telephones
     *
     * @return BaseTelephone[] $telephones
     */
    public function getTelephones()
    {
        return $this->telephones;
    }

    /**
     * @param BaseAdresse $adresse
     * @return self
     */
    public function addAdresse($adresse)
    {
        if($adresse !== null && !$adresse->isEmpty()) {
            $adresse->setContactInformation($this);
            $this->adresses[] = $adresse;
        }

        return $this;
    }

    /**
     * @param BaseAdresse $adresse
     * @return $this
     */
    public function removeAdresse(BaseAdresse $adresse) {

        foreach($this->adresses as $key => $item) {
            if($adresse->equals($item))
                unset($this->adresses[$key]);
        }
        return $this;
    }

    /**
     * Get adresse
     *
     * @return BaseAdresse[] $adresse
     */
    public function getAdresses()
    {
        return $this->adresses;
    }
}