<?php

namespace NetBS\SecureBundle\Mapping;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use NetBS\CoreBundle\Utils\StrUtil;
use NetBS\FichierBundle\Mapping\BaseMembre;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @ORM\MappedSuperclass()
 * @UniqueEntity(fields={"membre"})
 */
class BaseUser implements
    \Serializable,
    EquatableInterface,
    UserInterface
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string
     *
     * @ORM\Column(name="username", type="string", length=255, unique=true)
     */
    protected $username;

    /**
     * @var string
     *
     * @ORM\Column(name="password", type="string", length=255)
     */
    protected $password;

    /**
     * @var string
     * @Assert\Email
     * @ORM\Column(name="email", type="string", length=255, nullable=true, unique=true)
     */
    protected $email;

    /**
     * @var bool
     *
     * @ORM\Column(name="isActive", type="boolean")
     */
    protected $isActive;

    /**
     * @var BaseMembre
     */
    protected $membre;

    /**
     * @var \DateTime
     *
     * @ORM\Column(name="dateAdded", type="datetime")
     */
    protected $dateAdded;

    /**
     * @var string
     *
     * @ORM\Column(name="salt", type="string", length=255, nullable=true)
     */
    protected $salt;

    /**
     * @var BaseRole[]
     */
    protected $roles;

    /**
     * @var BaseAutorisation[]
     */
    protected $autorisations;

    public function __construct()
    {
        $this->isActive         = true;
        $this->dateAdded        = new \DateTime();
        $this->roles            = new ArrayCollection();
        $this->autorisations    = new ArrayCollection();
        $this->salt             = sha1(StrUtil::randomString() . uniqid());
    }

    public function __toString()
    {
        return $this->membre ? $this->membre->getFullName() : $this->getUsername();
    }

    /**
     * @param UserInterface $user
     * @return bool
     */
    public function isEqualTo(UserInterface $user)
    {
        return $user instanceof BaseUser && $user->getId() === $this->getId();
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
     * @return int|null
     */
    public function getMembreId() {

        return $this->membre ? $this->membre->getId() : null;
    }

    /**
     * Set username
     *
     * @param string $username
     *
     * @return BaseUser
     */
    public function setUsername($username)
    {
        $this->username = $username;

        return $this;
    }

    /**
     * Get username
     *
     * @return string
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Set password
     *
     * @param string $password
     *
     * @return BaseUser
     */
    public function setPassword($password)
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Get password
     *
     * @return string
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Set membre
     *
     * @param BaseMembre $membre
     * @return self
     */
    public function setMembre($membre)
    {
        $this->membre = $membre;
        return $this;
    }

    /**
     * Get membre
     *
     * @return BaseMembre
     */
    public function getMembre()
    {
        return $this->membre;
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
     * @param string $email
     * @return self
     */
    public function setEmail($email)
    {
        $this->email = $email;
        return $this;
    }

    public function getSendableEmail() {

        if($this->email)
            return $this->email;

        if($this->getMembre() && $this->getMembre()->getSendableEmail())
            return $this->getMembre()->getSendableEmail()->getEmail();
    }

    /**
     * Set isActive
     *
     * @param boolean $isActive
     *
     * @return self
     */
    public function setIsActive($isActive)
    {
        $this->isActive = $isActive;

        return $this;
    }

    /**
     * Get isActive
     *
     * @return bool
     */
    public function getIsActive()
    {
        return $this->isActive;
    }

    /**
     * Set dateAdded
     *
     * @param \DateTime $dateAdded
     *
     * @return self
     */
    public function setDateAdded($dateAdded)
    {
        $this->dateAdded = $dateAdded;

        return $this;
    }

    /**
     * Get dateAdded
     *
     * @return \DateTime
     */
    public function getDateAdded()
    {
        return $this->dateAdded;
    }

    /**
     * Get roles
     *
     * @return BaseRole[]
     */
    public function getAllRoles()
    {
        $roles  = [];
        foreach($this->roles as $role)
            $roles = array_merge($roles, $role->getChildrenRecursive());

        if($this->membre)
            foreach($this->getMembre()->getActivesAttributions() as $attribution)
                foreach($attribution->getFonction()->getRoles() as $role)
                    $roles = array_merge($roles, $role->getChildrenRecursive());

        return $roles;
    }

    public function getRoles()
    {
        return $this->roles->toArray();
    }

    public function getRolesAsString() {

        $roles  = [];
        foreach ($this->getAllRoles() as $role)
            $roles[] = $role->getRole();

        return $roles;
    }

    /**
     * @return BaseRole[]
     */
    public function getDirectRoles() {

        return $this->roles->toArray();
    }

    /**
     * @param $rolestr
     * @return bool
     */
    public function hasRole($rolestr) {

        foreach($this->getAllRoles() as $role)
            if($role->getRole() === $rolestr)
                return true;

        return false;
    }

    public function addRole(BaseRole $role) {

        $this->roles[] = $role;
        return $this;
    }

    public function removeRole(BaseRole $role) {

        $this->roles->removeElement($role);
        return $this;
    }

    /**
     * Get salt
     *
     * @return string
     */
    public function getSalt()
    {
        return $this->salt;
    }

    public function eraseCredentials()
    {
    }

    public function serialize()
    {
        return serialize(array(
            $this->id,
            $this->username,
            $this->password
        ));
    }

    public function unserialize($serialized)
    {
        list (
            $this->id,
            $this->username,
            $this->password,
            ) = unserialize($serialized);
    }

    public function isAccountNonExpired()
    {
        return true;
    }

    public function isAccountNonLocked()
    {
        return $this->isActive;
    }

    public function isCredentialsNonExpired()
    {
        return true;
    }

    public function isEnabled()
    {
        return $this->isActive;
    }

    /**
     * @return BaseAutorisation[]
     */
    public function getAutorisations()
    {
        return $this->autorisations;
    }

    public function addAutorisation(BaseAutorisation $autorisation) {
        $this->autorisations->add($autorisation);
    }

    public function removeAutorisation(BaseAutorisation $autorisation) {
        $this->autorisations->removeElement($autorisation);
    }
}

