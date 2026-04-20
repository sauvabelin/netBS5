<?php

namespace NetBS\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;
use NetBS\SecureBundle\Mapping\BaseUser;

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

    #[ORM\Column(name: 'id', type: 'integer')]
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'AUTO')]
    protected $id;

    #[ORM\Column(name: 'entity_class', type: 'string', length: 255)]
    protected $entityClass;

    #[ORM\Column(name: 'entity_id', type: 'integer')]
    protected $entityId;

    #[ORM\Column(name: 'action', type: 'string', length: 20)]
    protected $action;

    #[ORM\Column(name: 'property', type: 'string', length: 255, nullable: true)]
    protected $property;

    #[ORM\Column(name: 'old_value', type: 'text', nullable: true)]
    protected $oldValue;

    #[ORM\Column(name: 'new_value', type: 'text', nullable: true)]
    protected $newValue;

    #[ORM\Column(name: 'display_name', type: 'string', length: 255)]
    protected $displayName;

    protected $user;

    public function getId()
    {
        return $this->id;
    }

    public function getEntityClass()
    {
        return $this->entityClass;
    }

    public function setEntityClass($entityClass)
    {
        $this->entityClass = $entityClass;
        return $this;
    }

    public function getEntityId()
    {
        return $this->entityId;
    }

    public function setEntityId($entityId)
    {
        $this->entityId = $entityId;
        return $this;
    }

    public function getAction()
    {
        return $this->action;
    }

    public function setAction($action)
    {
        $this->action = $action;
        return $this;
    }

    public function getProperty()
    {
        return $this->property;
    }

    public function setProperty($property)
    {
        $this->property = $property;
        return $this;
    }

    public function getOldValue()
    {
        return $this->oldValue;
    }

    public function setOldValue($oldValue)
    {
        $this->oldValue = $oldValue;
        return $this;
    }

    public function getNewValue()
    {
        return $this->newValue;
    }

    public function setNewValue($newValue)
    {
        $this->newValue = $newValue;
        return $this;
    }

    public function getDisplayName()
    {
        return $this->displayName;
    }

    public function setDisplayName($displayName)
    {
        $this->displayName = $displayName;
        return $this;
    }

    public function getUser(): ?BaseUser
    {
        return $this->user;
    }

    public function setUser(?BaseUser $user)
    {
        $this->user = $user;
        return $this;
    }

    /**
     * e.g. "NetBS\FichierBundle\Entity\Attribution" -> "Attribution"
     */
    public function getEntityShortClass(): string
    {
        $parts = explode('\\', $this->entityClass);
        return end($parts);
    }
}
