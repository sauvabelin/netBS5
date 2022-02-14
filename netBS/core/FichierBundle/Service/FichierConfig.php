<?php

namespace NetBS\FichierBundle\Service;

use NetBS\FichierBundle\Mapping\BaseAdresse;
use NetBS\FichierBundle\Mapping\BaseAttribution;
use NetBS\FichierBundle\Mapping\BaseContactInformation;
use NetBS\FichierBundle\Mapping\BaseDistinction;
use NetBS\FichierBundle\Mapping\BaseEmail;
use NetBS\FichierBundle\Mapping\BaseFamille;
use NetBS\FichierBundle\Mapping\BaseFonction;
use NetBS\FichierBundle\Mapping\BaseGeniteur;
use NetBS\FichierBundle\Mapping\BaseGroupe;
use NetBS\FichierBundle\Mapping\BaseGroupeCategorie;
use NetBS\FichierBundle\Mapping\BaseGroupeType;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\FichierBundle\Mapping\BaseObtentionDistinction;
use NetBS\FichierBundle\Mapping\BaseTelephone;

class FichierConfig
{
    /**
     * @var array
     */
    protected $config;

    public function __construct(array $config)
    {
        if(!is_subclass_of($config['entities']['membre_class'], BaseMembre::class))
            throw new \Exception("Redefined 'membre' class must extend " . BaseMembre::class);

        if(!is_subclass_of($config['entities']['famille_class'], BaseFamille::class))
            throw new \Exception("Redefined 'famille' class must extend " . BaseFamille::class);

        if(!is_subclass_of($config['entities']['attribution_class'], BaseAttribution::class))
            throw new \Exception("Redefined 'attribution' class must extend " . BaseAttribution::class);

        if(!is_subclass_of($config['entities']['obtention_distinction_class'], BaseObtentionDistinction::class))
            throw new \Exception("Redefined 'obtention distinction' class must extend " . BaseObtentionDistinction::class);

        if(!is_subclass_of($config['entities']['fonction_class'], BaseFonction::class))
            throw new \Exception("Redefined 'fonction' class must extend " . BaseFonction::class);

        if(!is_subclass_of($config['entities']['groupe_class'], BaseGroupe::class))
            throw new \Exception("Redefined 'groupe' class must extend " . BaseGroupe::class);

        if(!is_subclass_of($config['entities']['distinction_class'], BaseDistinction::class))
            throw new \Exception("Redefined 'distinction' class must extend " . BaseDistinction::class);

        if(!is_subclass_of($config['entities']['geniteur_class'], BaseGeniteur::class))
            throw new \Exception("Redefined 'geniteur' class must extend " . BaseGeniteur::class);

        if(!is_subclass_of($config['entities']['groupe_type_class'], BaseGroupeType::class))
            throw new \Exception("Redefined 'groupe type' class must extend " . BaseGroupeType::class);

        if(!is_subclass_of($config['entities']['groupe_categorie_class'], BaseGroupeCategorie::class))
            throw new \Exception("Redefined 'groupe categorie' class must extend " . BaseGroupeCategorie::class);

        if(!is_subclass_of($config['entities']['adresse_class'], BaseAdresse::class))
            throw new \Exception("Redefined 'adresse' class must extend " . BaseAdresse::class);

        if(!is_subclass_of($config['entities']['email_class'], BaseEmail::class))
            throw new \Exception("Redefined 'email' class must extend " . BaseEmail::class);

        if(!is_subclass_of($config['entities']['telephone_class'], BaseTelephone::class))
            throw new \Exception("Redefined 'telephone' class must extend " . BaseTelephone::class);

        if(!is_subclass_of($config['entities']['contact_information_class'], BaseContactInformation::class))
            throw new \Exception("Redefined 'contact information' class must extend " . BaseContactInformation::class);

        $this->config   = $config;
    }

    public function getMembreClass() {

        return $this->config['entities']['membre_class'];
    }

    public function getFamilleClass() {

        return $this->config['entities']['famille_class'];
    }

    public function getAttributionClass() {

        return $this->config['entities']['attribution_class'];
    }

    public function getObtentionDistinctionClass() {

        return $this->config['entities']['obtention_distinction_class'];
    }

    public function getFonctionClass() {

        return $this->config['entities']['fonction_class'];
    }

    public function getGroupeClass() {

        return $this->config['entities']['groupe_class'];
    }

    public function getDistinctionClass() {

        return $this->config['entities']['distinction_class'];
    }

    public function getGeniteurClass() {

        return $this->config['entities']['geniteur_class'];
    }

    public function getGroupeTypeClass() {

        return $this->config['entities']['groupe_type_class'];
    }

    public function getGroupeCategorieClass() {

        return $this->config['entities']['groupe_categorie_class'];
    }

    public function getAdresseClass() {

        return $this->config['entities']['adresse_class'];
    }

    public function getTelephoneClass() {

        return $this->config['entities']['telephone_class'];
    }

    public function getEmailClass() {

        return $this->config['entities']['email_class'];
    }

    public function getContactInformationClass() {

        return $this->config['entities']['contact_information_class'];
    }

    public function createMembre() {

        $class  = $this->getMembreClass();

        /** @var BaseMembre $membre */
        $membre = new $class();
        $membre->setContactInformation($this->createContactInformation());

        return $membre;
    }

    public function createFamille() {

        $class  = $this->getFamilleClass();

        /** @var BaseFamille $famille */
        $famille = new $class();
        $famille->setContactInformation($this->createContactInformation());

        return $famille;
    }

    public function createGeniteur() {

        $class  = $this->getGeniteurClass();

        /** @var BaseGeniteur $geniteur */
        $geniteur   = new $class();
        $geniteur->setContactInformation($this->createContactInformation());

        return $geniteur;
    }

    public function createContactInformation() {

        $class  = $this->getContactInformationClass();
        return new $class();
    }

    /**
     * @return BaseAdresse
     */
    public function createAdresse() {

        $class  = $this->getAdresseClass();
        return new $class();
    }

    /**
     * @param null $email
     * @return BaseEmail
     */
    public function createEmail($email = null) {

        $class  = $this->getEmailClass();
        return new $class($email);
    }

    /**
     * @param null $telephone
     * @return BaseTelephone
     */
    public function createTelephone($telephone = null) {

        $class  = $this->getTelephoneClass();
        return new $class($telephone);
    }

    /**
     * @return BaseAttribution
     */
    public function createAttribution() {

        $class  = $this->getAttributionClass();
        return new $class();
    }

    /**
     * @return BaseFonction
     */
    public function createFonction() {

        $class  = $this->getFonctionClass();
        return new $class();
    }

    /**
     * @param null $nom
     * @return BaseDistinction
     */
    public function createDistinction($nom = null) {

        $class  = $this->getDistinctionClass();
        return new $class($nom);
    }

    /**
     * @return BaseObtentionDistinction
     */
    public function createObtentionDistinction() {

        $class  = $this->getObtentionDistinctionClass();
        return new $class();
    }

    /**
     * @return BaseGroupeType
     */
    public function createGroupeType() {

        $class  = $this->getGroupeTypeClass();
        return new $class();
    }

    /**
     * @param null $nom
     * @return BaseGroupeCategorie
     */
    public function createGroupeCategorie($nom = null) {

        $class  = $this->getGroupeCategorieClass();
        return new $class($nom);
    }

    /**
     * @return BaseGroupe
     */
    public function createGroupe() {

        $class  = $this->getGroupeClass();
        return new $class();
    }
}
