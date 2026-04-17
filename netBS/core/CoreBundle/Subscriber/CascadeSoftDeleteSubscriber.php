<?php

namespace NetBS\CoreBundle\Subscriber;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\UnitOfWork;

/**
 * Handles cascading soft-delete and restore across Doctrine associations
 * that have cascade: ['remove'] in their metadata.
 *
 * When an entity's deletedAt field is set (soft-delete), all cascade-remove
 * children that have a deletedAt field are also soft-deleted with the same timestamp.
 *
 * When an entity's deletedAt field is cleared (restore), all cascade-remove
 * children whose deletedAt matches the parent's original timestamp are also restored.
 */
class CascadeSoftDeleteSubscriber implements EventSubscriber
{
    public function getSubscribedEvents(): array
    {
        return [Events::onFlush];
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em  = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        // Collect entities whose deletedAt field changed in this flush
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $changeSet = $uow->getEntityChangeSet($entity);

            if (!array_key_exists('deletedAt', $changeSet)) {
                continue;
            }

            $oldValue = $changeSet['deletedAt'][0];
            $newValue = $changeSet['deletedAt'][1];

            if ($oldValue === null && $newValue instanceof \DateTimeInterface) {
                // Soft-delete: propagate the same timestamp to cascade children
                $this->cascadeSoftDelete($entity, $newValue, $em, $uow);
            } elseif ($oldValue instanceof \DateTimeInterface && $newValue === null) {
                // Restore: undelete children that were deleted together with this entity
                $this->cascadeRestore($entity, $oldValue, $em, $uow);
            }
        }
    }

    /**
     * Recursively soft-delete cascade-remove children that have a deletedAt
     * field and are not already deleted.
     */
    private function cascadeSoftDelete(
        object $entity,
        \DateTimeInterface $deletedAt,
        EntityManagerInterface $em,
        UnitOfWork $uow
    ): void {
        $metadata     = $em->getClassMetadata(get_class($entity));
        $associations = $this->getCascadeRemoveAssociations($metadata);

        foreach ($associations as $assocMapping) {
            $children = $this->getAssociationTargets($entity, $assocMapping);

            foreach ($children as $child) {
                if (!$this->hasSoftDelete($child)) {
                    continue;
                }

                // Skip if already soft-deleted
                if ($child->getDeletedAt() !== null) {
                    continue;
                }

                $child->setDeletedAt($deletedAt);
                $childMetadata = $em->getClassMetadata(get_class($child));
                $uow->recomputeSingleEntityChangeSet($childMetadata, $child);

                // Recurse into the child's own cascade associations
                $this->cascadeSoftDelete($child, $deletedAt, $em, $uow);
            }
        }
    }

    /**
     * Recursively restore cascade-remove children whose deletedAt timestamp
     * matches the parent's original deletedAt (i.e. they were deleted together).
     *
     * Temporarily disables the softdeleteable filter so hidden entities are reachable.
     */
    private function cascadeRestore(
        object $entity,
        \DateTimeInterface $originalDeletedAt,
        EntityManagerInterface $em,
        UnitOfWork $uow
    ): void {
        $filters        = $em->getFilters();
        $filterEnabled  = $filters->isEnabled('softdeleteable');

        if ($filterEnabled) {
            $filters->disable('softdeleteable');
        }

        try {
            $this->doRestore($entity, $originalDeletedAt, $em, $uow);
        } finally {
            if ($filterEnabled) {
                $filters->enable('softdeleteable');
            }
        }
    }

    /**
     * Inner recursive restore logic (filter already disabled by caller).
     */
    private function doRestore(
        object $entity,
        \DateTimeInterface $originalDeletedAt,
        EntityManagerInterface $em,
        UnitOfWork $uow
    ): void {
        $metadata     = $em->getClassMetadata(get_class($entity));
        $associations = $this->getCascadeRemoveAssociations($metadata);

        foreach ($associations as $assocMapping) {
            $targetClass    = $assocMapping['targetEntity'];
            $targetMetadata = $em->getClassMetadata($targetClass);

            // Re-fetch children from the database (bypassing the now-disabled filter)
            $children = $this->fetchAssociationTargets($entity, $assocMapping, $em);

            foreach ($children as $child) {
                if (!$this->hasSoftDelete($child)) {
                    continue;
                }

                $childDeletedAt = $child->getDeletedAt();

                // Only restore children that were deleted at the same moment as the parent
                if ($childDeletedAt === null) {
                    continue;
                }

                if ($childDeletedAt->getTimestamp() !== $originalDeletedAt->getTimestamp()) {
                    continue;
                }

                $child->setDeletedAt(null);
                $uow->recomputeSingleEntityChangeSet($targetMetadata, $child);

                // Recurse, carrying the child's original deletedAt as the matching key
                $this->doRestore($child, $childDeletedAt, $em, $uow);
            }
        }
    }

    /**
     * Returns all association mappings on the given ClassMetadata that have
     * 'remove' in their cascade list (equivalent to cascade: ['remove']).
     *
     * @return array<int, array<string, mixed>>
     */
    private function getCascadeRemoveAssociations(ClassMetadata $metadata): array
    {
        $result = [];

        foreach ($metadata->associationMappings as $mapping) {
            if (!empty($mapping['isCascadeRemove'])) {
                $result[] = $mapping;
            }
        }

        return $result;
    }

    /**
     * Returns the target entity/entities for the given association on $entity.
     * Works for both OneToOne and OneToMany.
     *
     * @return iterable<object>
     */
    private function getAssociationTargets(object $entity, array $assocMapping): iterable
    {
        $fieldName = $assocMapping['fieldName'];
        $getter    = 'get' . ucfirst($fieldName);

        if (!method_exists($entity, $getter)) {
            return [];
        }

        $value = $entity->$getter();

        if ($value === null) {
            return [];
        }

        if (is_iterable($value)) {
            return $value;
        }

        return [$value];
    }

    /**
     * Fetches association targets from the database directly.
     * Needed during restore so that soft-deleted children (hidden by the filter
     * before we disabled it) are retrieved correctly.
     *
     * @return iterable<object>
     */
    private function fetchAssociationTargets(
        object $entity,
        array $assocMapping,
        EntityManagerInterface $em
    ): iterable {
        $type = $assocMapping['type'];

        // For OneToOne / ManyToOne: use the already-loaded value (proxy is fine since filter is off)
        if (in_array($type, [ClassMetadata::ONE_TO_ONE, ClassMetadata::MANY_TO_ONE], true)) {
            return $this->getAssociationTargets($entity, $assocMapping);
        }

        // For OneToMany / ManyToMany: query the repository to bypass Doctrine's collection cache
        if (in_array($type, [ClassMetadata::ONE_TO_MANY, ClassMetadata::MANY_TO_MANY], true)) {
            $targetClass  = $assocMapping['targetEntity'];
            $mappedBy     = $assocMapping['mappedBy'] ?? null;

            if ($mappedBy === null) {
                // Owning side without mappedBy — fall back to the loaded collection
                return $this->getAssociationTargets($entity, $assocMapping);
            }

            return $em->getRepository($targetClass)->findBy([$mappedBy => $entity]);
        }

        return [];
    }

    /**
     * Returns true if the given entity has a setDeletedAt / getDeletedAt pair
     * (i.e. it uses the SoftDeleteableEntity trait or equivalent).
     */
    private function hasSoftDelete(object $entity): bool
    {
        return method_exists($entity, 'getDeletedAt') && method_exists($entity, 'setDeletedAt');
    }
}
