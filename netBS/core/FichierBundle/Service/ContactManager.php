<?php

namespace NetBS\FichierBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use NetBS\FichierBundle\Mapping\BaseAdresse;
use NetBS\FichierBundle\Mapping\BaseContactInformation;
use NetBS\FichierBundle\Mapping\BaseEmail;
use NetBS\FichierBundle\Mapping\BaseFamille;
use NetBS\FichierBundle\Mapping\BaseGeniteur;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\FichierBundle\Mapping\BaseTelephone;

class ContactManager
{
    protected $em;

    protected $config;

    public function __construct(EntityManagerInterface $em, FichierConfig $config)
    {
        $this->em       = $em;
        $this->config   = $config;
    }

    /**
     * @param BaseAdresse|BaseEmail|BaseTelephone $item
     * @return BaseMembre|BaseFamille|BaseGeniteur|null
     */
    public function findOwner($item) {

        $contactInfo    = $item->getContactInformation();

        $membre         = $this->search($this->config->getMembreClass(), $contactInfo);
        if($membre) return $membre;

        $famille        = $this->search($this->config->getFamilleClass(), $contactInfo);
        if($famille) return $famille;

        $geniteur       = $this->search($this->config->getGeniteurClass(), $contactInfo);
        if($geniteur) return $geniteur;

        return null;
    }

    protected function search($class, BaseContactInformation $ci) {

        $items = $this->em->createQueryBuilder()->select('item')
            ->from($class, 'item')
            ->where('item.contactInformation = :ci')
            ->setParameter('ci', $ci)
            ->getQuery()
            ->execute();

        return count($items) > 0 ? $items[0] : null;
    }
}
