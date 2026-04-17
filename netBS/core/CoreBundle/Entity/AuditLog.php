<?php

namespace NetBS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use NetBS\SecureBundle\Mapping\BaseUser;

/**
 * Class AuditLog
 * @package NetBS\CoreBundle\Entity
 */
#[ORM\Table(name: 'netbs_core_audit_log', indexes: [
    new ORM\Index(name: 'idx_audit_entity', columns: ['entity_class', 'entity_id']),
    new ORM\Index(name: 'idx_audit_action', columns: ['action']),
    new ORM\Index(name: 'idx_audit_created', columns: ['created_at']),
])]
#[ORM\Entity]
class AuditLog
{
    use TimestampableEntity;

    const ACTION_CREATE  = 'create';
    const ACTION_UPDATE  = 'update';
    const ACTION_DELETE  = 'delete';
    const ACTION_RESTORE = 'restore';

    /**
     * @var int
     */
    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    protected $id;

    /**
     * @var string
     */
    #[ORM\Column(name: 'entity_class', type: 'string', length: 255)]
    protected $entityClass;

    /**
     * @var int
     */
    #[ORM\Column(name: 'entity_id', type: 'integer')]
    protected $entityId;

    /**
     * @var string
     */
    #[ORM\Column(name: 'action', type: 'string', length: 20)]
    protected $action;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'property', type: 'string', length: 255, nullable: true)]
    protected $property;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'old_value', type: 'text', nullable: true)]
    protected $oldValue;

    /**
     * @var string|null
     */
    #[ORM\Column(name: 'new_value', type: 'text', nullable: true)]
    protected $newValue;

    /**
     * @var string
     */
    #[ORM\Column(name: 'display_name', type: 'string', length: 255)]
    protected $displayName;

    /**
     * @var BaseUser
     */
    protected $user;

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
     * @return string
     */
    public function getEntityClass()
    {
        return $this->entityClass;
    }

    /**
     * @param string $entityClass
     * @return $this
     */
    public function setEntityClass($entityClass)
    {
        $this->entityClass = $entityClass;
        return $this;
    }

    /**
     * @return int
     */
    public function getEntityId()
    {
        return $this->entityId;
    }

    /**
     * @param int $entityId
     * @return $this
     */
    public function setEntityId($entityId)
    {
        $this->entityId = $entityId;
        return $this;
    }

    /**
     * @return string
     */
    public function getAction()
    {
        return $this->action;
    }

    /**
     * @param string $action
     * @return $this
     */
    public function setAction($action)
    {
        $this->action = $action;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getProperty()
    {
        return $this->property;
    }

    /**
     * @param string|null $property
     * @return $this
     */
    public function setProperty($property)
    {
        $this->property = $property;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getOldValue()
    {
        return $this->oldValue;
    }

    /**
     * @param string|null $oldValue
     * @return $this
     */
    public function setOldValue($oldValue)
    {
        $this->oldValue = $oldValue;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getNewValue()
    {
        return $this->newValue;
    }

    /**
     * @param string|null $newValue
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
     * Returns just the short class name without namespace.
     * e.g. "NetBS\FichierBundle\Entity\Attribution" -> "Attribution"
     *
     * @return string
     */
    public function getEntityShortClass(): string
    {
        $parts = explode('\\', $this->entityClass);
        return end($parts);
    }
}
