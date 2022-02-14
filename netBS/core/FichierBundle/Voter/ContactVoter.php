<?php

namespace NetBS\FichierBundle\Voter;

use Doctrine\Common\Util\ClassUtils;
use NetBS\FichierBundle\Entity\Adresse;
use NetBS\FichierBundle\Entity\Email;
use NetBS\FichierBundle\Entity\Telephone;
use NetBS\FichierBundle\Mapping\BaseFamille;
use NetBS\FichierBundle\Mapping\BaseGeniteur;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\FichierBundle\Service\ContactManager;
use NetBS\FichierBundle\Service\FichierConfig;
use NetBS\SecureBundle\Mapping\BaseUser;

class ContactVoter extends MembreFamilleVoter
{
    /**
     * @var ContactManager
     */
    protected $cm;

    public function __construct(FichierConfig $config, ContactManager $contactManager)
    {
        parent::__construct($config);

        $this->cm   = $contactManager;
    }

    protected function supportClass()
    {
        return [
            Adresse::class,
            Telephone::class,
            Email::class
        ];
    }

    /**
     * Accept or denies the given crud operation on the given subject for the given user
     * @param string $operation a CRUD operation
     * @param Adresse|Telephone|Email $subject
     * @param BaseUser $user
     * @return bool
     */
    protected function accept($operation, $subject, BaseUser $user)
    {
        if(!in_array(ClassUtils::getClass($subject), [Adresse::class, Email::class, Telephone::class]))
            return false;

        $owner  = $this->cm->findOwner($subject);
        return $this->handleOwnerVerification($operation, $owner, $user);
    }

    /**
     * @param $operation
     * @param $owner
     * @param BaseUser $user
     * @return bool
     */
    protected function handleOwnerVerification($operation, $owner, BaseUser $user) {

        if($owner instanceof BaseMembre)
            return parent::acceptMembre($operation, $owner, $user);
        elseif($owner instanceof BaseFamille)
            return parent::acceptFamille($operation, $owner, $user);
        elseif($owner instanceof BaseGeniteur)
            return parent::acceptGeniteur($operation, $owner, $user);
        else
            return false;
    }
}