<?php

namespace NetBS\SecureBundle\Mapping;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use NetBS\FichierBundle\Mapping\BaseGroupe;
use Symfony\Component\Security\Core\Role\Role;

/**
 * @ORM\MappedSuperclass()
 */
class BaseAutorisation
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
     * @var BaseUser
     */
    protected $user;

    /**
     * @var BaseGroupe
     */
    protected $groupe;

    /**
     * @var BaseRole[]
     * @ORM\JoinTable(name="netbs_autorisations_roles",
     *     joinColumns={@ORM\JoinColumn(name="autorisation_id", referencedColumnName="id")},
     *     inverseJoinColumns={@ORM\JoinColumn(name="role_id", referencedColumnName="id")})
     */
    protected $roles;

    public function __construct()
    {
        $this->roles = new ArrayCollection();
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return BaseUser
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param BaseUser $user
     */
    public function setUser($user)
    {
        $this->user = $user;
    }

    /**
     * @return BaseGroupe
     */
    public function getGroupe()
    {
        return $this->groupe;
    }

    /**
     * @param BaseGroupe $groupe
     */
    public function setGroupe($groupe)
    {
        $this->groupe = $groupe;
    }

    /**
     * @return BaseRole[]
     */
    public function getRoles()
    {
        return $this->roles;
    }

    /**
     * @param BaseRole[] $roles
     */
    public function setRoles($roles)
    {
        $this->roles = $roles;
    }

}

