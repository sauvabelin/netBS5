<?php

namespace NetBS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use NetBS\SecureBundle\Mapping\BaseUser;

/**
 * Class LoggedChange
 * @package NetBS\CoreBundle\Entity
 * @ORM\Table(name="netbs_core_logged_changes")
 * @ORM\Entity
 */
class LoggedChange
{
    use TimestampableEntity;

    const   WAITING     = 'waiting';
    const   APPROVED    = 'approved';
    const   REJECTED    = 'rejected';
    /**
     * @var int
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;

    /**
     * @var string
     * @ORM\Column(name="action", type="string", length=255)
     */
    protected $action;

    /**
     * @var string
     * @ORM\Column(name="display_name", type="string", length=255)
     */
    protected $displayName;

    /**
     * @var string
     * @ORM\Column(name="representation", type="text")
     */
    protected $representation;

    /**
     * @var int
     * @ORM\Column(name="object_id", type="integer", length=11)
     */
    protected $objectId;

    /**
     * @var string
     * @ORM\Column(name="object_class", type="string", length=255)
     */
    protected $objectClass;

    /**
     * @var string
     * @ORM\Column(name="property", type="string", length=255, nullable=true)
     */
    protected $property;

    /**
     * @var string
     * @ORM\Column(name="old_value", type="text", nullable=true)
     */
    protected $oldValue;

    /**
     * @var string
     * @ORM\Column(name="new_value", type="text", nullable=true)
     */
    protected $newValue;

    /**
     * @var string
     * @ORM\Column(name="status", type="string", length=255)
     */
    protected $status;

    /**
     * @var BaseUser
     */
    protected $user;

    public function __construct()
    {
        $this->status = self::WAITING;
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set action
     *
     * @param string $action
     *
     * @return LoggedChange
     */
    public function setAction($action)
    {
        $this->action = $action;

        return $this;
    }

    /**
     * Get action
     *
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * Set objectId
     *
     * @param integer $objectId
     *
     * @return LoggedChange
     */
    public function setObjectId($objectId)
    {
        $this->objectId = $objectId;

        return $this;
    }

    /**
     * Get objectId
     *
     * @return integer
     */
    public function getObjectId()
    {
        return $this->objectId;
    }

    /**
     * Set objectClass
     *
     * @param string $objectClass
     *
     * @return LoggedChange
     */
    public function setObjectClass($objectClass)
    {
        $this->objectClass = $objectClass;

        return $this;
    }

    /**
     * Get objectClass
     *
     * @return string
     */
    public function getObjectClass()
    {
        return $this->objectClass;
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
     * @return $this
     */
    public function setUser($user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param string $status
     * @return $this
     */
    public function setStatus($status)
    {
        $this->status = $status;
        return $this;
    }

    /**
     * @return string
     */
    public function getOldValue()
    {
        return $this->oldValue;
    }

    /**
     * @param string $oldValue
     * @return $this
     */
    public function setOldValue($oldValue)
    {
        $this->oldValue = $oldValue;
        return $this;
    }

    /**
     * @return string
     */
    public function getNewValue()
    {
        return $this->newValue;
    }

    /**
     * @param string $newValue
     * @return $this
     */
    public function setNewValue($newValue)
    {
        $this->newValue = $newValue;
        return $this;
    }

    /**
     * @return string
     */
    public function getProperty()
    {
        return $this->property;
    }

    /**
     * @param string $property
     * @return $this
     */
    public function setProperty($property)
    {
        $this->property = $property;
        return $this;
    }

    /**
     * @return string
     */
    public function getDisplayName()
    {
        return $this->displayName;
    }

    /**
     * @param string $displayName
     * @return $this
     */
    public function setDisplayName($displayName)
    {
        $this->displayName = $displayName;
        return $this;
    }

    /**
     * @return string
     */
    public function getRepresentation()
    {
        return $this->representation;
    }

    /**
     * @param string $representation
     * @return $this
     */
    public function setRepresentation($representation)
    {
        $this->representation = $representation;
        return $this;
    }
}
