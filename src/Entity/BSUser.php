<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use NetBS\FichierBundle\Mapping\BaseMembre;
use NetBS\SecureBundle\Mapping\BaseUser;

/**
 * User
 * @ORM\Table(name="sauvabelin_netbs_users")
 * @ORM\Entity
 */
class BSUser extends BaseUser
{
    const HAS_REDIRECT = 'has_a_mail_redirect';
    const HAS_ACCOUNT = 'has_an_account';

    //Nextcloud data
    /**
     * @var bool
     * @ORM\Column(name="nextcloud_account", type="boolean")
     */
    protected $nextcloudAccount = true;

    /**
     * @var bool
     * @ORM\Column(name="nextcloud_admin", type="boolean")
     */
    protected $nextcloudAdmin   = false;

    /**
     * Stammbox quota per user, base 10MB
     * @var int
     * @ORM\Column(name="nextcloud_quota", type="integer")
     */
    protected $nextcloudQuota   = 10485760;

    //Wikimedia data
    /**
     * @var bool
     * @ORM\Column(name="wiki_account", type="boolean")
     */
    protected $wikiAccount  = true;

    /**
     * @var bool
     * @ORM\Column(name="new_password_required", type="boolean")
     */
    protected $newPasswordRequired  = false;

    /**
     * @var bool
     * @ORM\Column(name="wiki_admin", type="boolean")
     */
    protected $wikiAdmin    = false;

    /**
     * @var string
     * @ORM\Column(name="email_bs", type="string", length=255, nullable=true)
     */
    protected $emailBS = null;

    /**
     * @return string
     */
    public function getEmailBS()
    {
        return $this->emailBS;
    }

    /**
     * @param string $emailBS
     */
    public function setEmailBS($emailBS)
    {
        $this->emailBS = $emailBS;
    }


    /**
     * @return bool
     */
    public function hasNextcloudAccount()
    {
        return $this->nextcloudAccount;
    }

    /**
     * @param bool $nextcloudAccount
     */
    public function setNextcloudAccount($nextcloudAccount)
    {
        $this->nextcloudAccount = $nextcloudAccount;
    }

    /**
     * @return bool
     */
    public function hasWikiAccount()
    {
        return $this->wikiAccount;
    }

    /**
     * @param bool $wikiAccount
     */
    public function setWikiAccount($wikiAccount)
    {
        $this->wikiAccount = $wikiAccount;
    }

    /**
     * @return bool
     */
    public function isNextcloudAdmin()
    {
        return $this->nextcloudAdmin;
    }

    /**
     * @param bool $nextcloudAdmin
     */
    public function setNextcloudAdmin($nextcloudAdmin)
    {
        $this->nextcloudAdmin = $nextcloudAdmin;
    }

    /**
     * @return bool
     */
    public function isWikiAdmin()
    {
        return $this->wikiAdmin;
    }

    /**
     * @param bool $wikiAdmin
     */
    public function setWikiAdmin($wikiAdmin)
    {
        $this->wikiAdmin = $wikiAdmin;
    }

    public function isInGroup($groupe) {
        return $this->membre ? $this->membre->isInGroup($groupe) : false;
    }

    public function isActif() {

        return $this->membre ? count($this->membre->getActivesAttributions()) > 0 && !$this->isInGroup('ADABS') : false;
    }

    public function isInscrit() {
        return $this->membre ? $this->membre->getStatut() === BaseMembre::INSCRIT : false;
    }

    public function hasDistinction($distinction) {

        return $this->membre ? $this->membre->hasDistinction($distinction) : false;
    }

    public function hasFonction($fonction) {

        return $this->membre ? $this->membre->hasFonction($fonction) : false;
    }

    public function hasFonctionInGroup($fonction, $groupe) {

        if(!$this->membre)
            return false;

        foreach($this->membre->getActivesAttributions() as $attribution)
            if($fonction === $attribution->getFonction()->getNom() && $groupe === $attribution->getGroupe()->getNom())
                return true;

        return false;
    }

    /**
     * @return bool
     */
    public function isNewPasswordRequired()
    {
        return $this->newPasswordRequired;
    }

    /**
     * @param bool $newPasswordRequired
     */
    public function setNewPasswordRequired($newPasswordRequired)
    {
        $this->newPasswordRequired = $newPasswordRequired;
    }

    /**
     * @return int
     */
    public function getNextcloudQuota()
    {
        return $this->nextcloudQuota;
    }

    /**
     * @param int $nextcloudQuota
     */
    public function setNextcloudQuota($nextcloudQuota)
    {
        $this->nextcloudQuota = $nextcloudQuota;
    }
}

