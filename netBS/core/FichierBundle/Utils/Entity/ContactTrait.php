<?php

namespace NetBS\FichierBundle\Utils\Entity;

use NetBS\FichierBundle\Mapping\BaseAdresse;
use NetBS\FichierBundle\Mapping\BaseContactInformation;
use NetBS\FichierBundle\Mapping\BaseEmail;
use NetBS\FichierBundle\Mapping\BaseTelephone;

trait ContactTrait
{
    /**
     * Add email
     *
     * @param BaseEmail $email
     */
    public function addEmail(BaseEmail $email)
    {
        if($email->getEmail())
            $this->getContactInformation()->addEmail($email);
    }

    /**
     * Remove email
     *
     * @param BaseEmail $email
     */
    public function removeEmail(BaseEmail $email)
    {
        $this->getContactInformation()->removeEmail($email);
    }

    /**
     * Get emails
     *
     * @return BaseEmail[] $emails
     */
    public function getEmails()
    {
        return $this->getContactInformation()->getEmails();
    }

    /**
     * Add telephone
     *
     * @param BaseTelephone $telephone
     */
    public function addTelephone(BaseTelephone $telephone)
    {
        if($telephone->getTelephone())
            $this->getContactInformation()->addTelephone($telephone);
    }

    /**
     * Remove telephone
     *
     * @param BaseTelephone $telephone
     */
    public function removeTelephone(BaseTelephone $telephone)
    {
        $this->getContactInformation()->removeTelephone($telephone);
    }

    /**
     * Get telephones
     *
     * @return BaseTelephone[] $telephones
     */
    public function getTelephones()
    {
        return $this->getContactInformation()->getTelephones();
    }

    /**
     * Set adresse
     *
     * @param BaseAdresse $adresse
     * @return self
     */
    public function addAdresse(BaseAdresse $adresse)
    {
        if($adresse->getRue())
            $this->getContactInformation()->addAdresse($adresse);

        return $this;
    }

    /**
     * @param BaseAdresse $adresse
     * @return $this
     */
    public function removeAdresse(BaseAdresse $adresse) {

        $this->getContactInformation()->removeAdresse($adresse);
        return $this;
    }

    /**
     * Get adresse
     *
     * @return BaseAdresse[] $adresse
     */
    public function getAdresses()
    {
        return $this->getContactInformation()->getAdresses();
    }

    /**
     * @return BaseContactInformation
     */
    public function getContactInformation() {

        return $this->contactInformation;
    }

    public function setContactInformation(BaseContactInformation $contactInformation) {

        $this->contactInformation   = $contactInformation;
    }
}