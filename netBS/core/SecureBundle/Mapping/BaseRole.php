<?php

namespace NetBS\SecureBundle\Mapping;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Role
 * @ORM\MappedSuperclass()
 */
class BaseRole
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
     * @ORM\Column(name="role", type="string", length=255)
     */
    protected $role;

    /**
     * @var int
     *
     * @ORM\Column(name="poids", type="integer")
     */
    protected $poids;

    /**
     * @var string
     *
     * @ORM\Column(name="description", type="text")
     */
    protected $description;

    /**
     * @var BaseRole
     */
    protected $parent;

    /**
     * @var BaseRole[]
     */
    protected $children;

    public function __construct($role = '', $poids = 0, $description = '')
    {
        $this->role         = $role;
        $this->poids        = $poids;
        $this->description  = $description;
        $this->children     = new ArrayCollection();
    }

    /**
     * @return string
     */
    public function __toString()
    {
        return $this->role;
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
     * Set role
     *
     * @param string $role
     *
     * @return self
     */
    public function setRole($role)
    {
        $this->role = $role;

        return $this;
    }

    /**
     * Get role
     *
     * @return string
     */
    public function getRole()
    {
        return $this->role;
    }

    /**
     * Set poids
     *
     * @param integer $poids
     *
     * @return self
     */
    public function setPoids($poids)
    {
        $this->poids = $poids;

        return $this;
    }

    /**
     * Get poids
     *
     * @return int
     */
    public function getPoids()
    {
        return $this->poids;
    }

    /**
     * Set description
     *
     * @param string $description
     *
     * @return self
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }

    public function setParent(BaseRole $role) {

        $this->parent   = $role;
        return $this;
    }

    public function getParent() {

        return $this->parent;
    }

    public function addChild(BaseRole $role) {

        $this->children[] = $role;
        $role->setParent($this);
        return $this;
    }

    public function removeChild(BaseRole $role) {

        $this->children->removeElement($role);
        return $this;
    }

    public function getChildren() {

        return $this->children;
    }

    public function getChildrenRecursive() {

        $roles  = [$this];

        foreach($this->children as $child)
            $roles  = array_merge($roles, $child->getChildrenRecursive());

        return $roles;
    }
}

