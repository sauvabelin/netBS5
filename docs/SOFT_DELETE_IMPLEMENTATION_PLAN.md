# Soft Delete Implementation Plan for Users and Membres

## Executive Summary

This document outlines a comprehensive implementation plan for adding soft delete functionality to the netBS5 application, specifically for `User` and `Membre` entities. The current system uses hard deletion, which permanently removes records and all associated data. Implementing soft delete will preserve data integrity, improve audit trail capabilities, and allow for data recovery.

---

## Table of Contents

1. [Current State Analysis](#1-current-state-analysis)
2. [Proposed Solution](#2-proposed-solution)
3. [Implementation Plan](#3-implementation-plan)
4. [Database Changes](#4-database-changes)
5. [Code Changes](#5-code-changes)
6. [Caveats and Considerations](#6-caveats-and-considerations)
7. [Migration Strategy](#7-migration-strategy)
8. [Testing Requirements](#8-testing-requirements)
9. [Rollback Plan](#9-rollback-plan)
10. [Timeline Estimate](#10-timeline-estimate)

---

## 1. Current State Analysis

### 1.1 User Entity

**Location:** `netBS/core/SecureBundle/Mapping/BaseUser.php`

**Current Deletion Behavior:**
- Hard delete via `UserManager::deleteUser()`
- Cascades deletion to: `LoggedChange`, `DynamicList`, `News`, `ExportConfiguration`, `Notification`, `UserLog`
- No recovery possible after deletion

**Existing Fields:**
- `isActive` (boolean) - Can disable users but not soft delete
- `dateAdded` (DateTime) - Account creation date
- No `deletedAt` field

### 1.2 Membre Entity

**Location:** `netBS/core/FichierBundle/Mapping/BaseMembre.php`

**Current Deletion Behavior:**
- Hard delete via `MembreController::removeMembreAction()`
- Triggers `RemoveMembreEvent` which cascades to:
  - Associated `User` account (hard deleted)
  - `Facture` and `Créance` records (hard deleted)
  - All contact information (hard deleted)
- No recovery possible after deletion

**Existing Fields:**
- `statut` - Member status (INSCRIT, DESINSCRIT, PAUSE, DECEDE, AUTRE)
- `desinscription` (DateTime) - Unregistration date
- No `deletedAt` field

### 1.3 Audit Trail Gaps

| Issue | Description |
|-------|-------------|
| ROLE_SG excluded | Super Admin changes are not logged |
| User entity not tracked | No LogRepresenter for User changes |
| No Blameable fields | No `created_by`/`updated_by` on entities |
| Deletion not reversible | Once deleted, audit trail references orphaned IDs |

### 1.4 Infrastructure Status

| Component | Status |
|-----------|--------|
| Gedmo SoftDeleteable Filter | Configured and enabled |
| Gedmo SoftDeleteable Listener | Configured |
| DoctrineMigrationsBundle | **NOT INSTALLED** |
| Entity annotations | Not using `@Gedmo\SoftDeleteable` |

---

## 2. Proposed Solution

### 2.1 Overview

Implement Gedmo SoftDeleteable on `User` and `Membre` entities, leveraging the already-configured Gedmo infrastructure. Add proper database migrations, update deletion logic, and fix audit trail gaps.

### 2.2 Scope

**In Scope:**
- Soft delete for `User` entity
- Soft delete for `Membre` entity
- Database migration infrastructure
- Audit trail improvements
- Admin interface for viewing/restoring deleted records
- Related entity handling strategy

**Out of Scope:**
- Soft delete for other entities (can be added later)
- Historical data recovery (already deleted records cannot be recovered)
- GDPR "right to be forgotten" hard delete (separate feature)

---

## 3. Implementation Plan

### Phase 1: Infrastructure Setup

#### 3.1.1 Install DoctrineMigrationsBundle

```bash
composer require doctrine/doctrine-migrations-bundle
```

#### 3.1.2 Configure Migrations

Create `config/packages/doctrine_migrations.yaml`:

```yaml
doctrine_migrations:
    migrations_paths:
        'DoctrineMigrations': '%kernel.project_dir%/migrations'
    storage:
        table_storage:
            table_name: 'doctrine_migration_versions'
    all_or_nothing: true
    transactional: true
    check_database_platform: true
```

#### 3.1.3 Create Migrations Directory

```bash
mkdir -p migrations
```

### Phase 2: Database Schema Changes

#### 3.2.1 User Table Migration

```php
// migrations/Version20240XXX_AddSoftDeleteToUser.php

public function up(Schema $schema): void
{
    $this->addSql('ALTER TABLE fos_user ADD deleted_at DATETIME DEFAULT NULL');
    $this->addSql('ALTER TABLE fos_user ADD deleted_by INT DEFAULT NULL');
    $this->addSql('CREATE INDEX IDX_USER_DELETED_AT ON fos_user (deleted_at)');
}

public function down(Schema $schema): void
{
    $this->addSql('DROP INDEX IDX_USER_DELETED_AT ON fos_user');
    $this->addSql('ALTER TABLE fos_user DROP deleted_at');
    $this->addSql('ALTER TABLE fos_user DROP deleted_by');
}
```

#### 3.2.2 Membre Table Migration

```php
// migrations/Version20240XXX_AddSoftDeleteToMembre.php

public function up(Schema $schema): void
{
    $this->addSql('ALTER TABLE netbs_fichier_membre ADD deleted_at DATETIME DEFAULT NULL');
    $this->addSql('ALTER TABLE netbs_fichier_membre ADD deleted_by INT DEFAULT NULL');
    $this->addSql('CREATE INDEX IDX_MEMBRE_DELETED_AT ON netbs_fichier_membre (deleted_at)');
}

public function down(Schema $schema): void
{
    $this->addSql('DROP INDEX IDX_MEMBRE_DELETED_AT ON netbs_fichier_membre');
    $this->addSql('ALTER TABLE netbs_fichier_membre DROP deleted_at');
    $this->addSql('ALTER TABLE netbs_fichier_membre DROP deleted_by');
}
```

### Phase 3: Entity Changes

#### 3.3.1 BaseUser Modifications

```php
// netBS/core/SecureBundle/Mapping/BaseUser.php

use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;

/**
 * @ORM\MappedSuperclass
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false, hardDelete=false)
 */
abstract class BaseUser implements UserInterface
{
    use SoftDeleteableEntity; // Adds $deletedAt field and methods

    /**
     * @ORM\ManyToOne(targetEntity="BaseUser")
     * @ORM\JoinColumn(name="deleted_by", referencedColumnName="id", nullable=true)
     */
    protected $deletedBy;

    // Getter/setter for deletedBy...
}
```

#### 3.3.2 BaseMembre Modifications

```php
// netBS/core/FichierBundle/Mapping/BaseMembre.php

use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;

/**
 * @ORM\MappedSuperclass
 * @Gedmo\SoftDeleteable(fieldName="deletedAt", timeAware=false, hardDelete=false)
 */
abstract class BaseMembre extends Personne
{
    use SoftDeleteableEntity; // Adds $deletedAt field and methods

    /**
     * @ORM\ManyToOne(targetEntity="NetBS\SecureBundle\Mapping\BaseUser")
     * @ORM\JoinColumn(name="deleted_by", referencedColumnName="id", nullable=true)
     */
    protected $deletedBy;

    // Getter/setter for deletedBy...
}
```

### Phase 4: Service Layer Changes

#### 3.4.1 UserManager Updates

```php
// netBS/core/SecureBundle/Service/UserManager.php

public function deleteUser(BaseUser $user): void
{
    // Record who deleted the user
    $currentUser = $this->tokenStorage->getToken()?->getUser();
    if ($currentUser instanceof BaseUser) {
        $user->setDeletedBy($currentUser);
    }

    // Soft delete related entities OR orphan them
    $this->handleRelatedEntities($user);

    // Gedmo will automatically set deletedAt on flush
    $this->em->flush();
}

private function handleRelatedEntities(BaseUser $user): void
{
    // Option A: Orphan related entities (keep but remove user reference)
    // Option B: Soft delete related entities
    // Option C: Keep as-is (references remain, queries filter by user.deletedAt)

    // Recommended: Orphan or keep, don't cascade soft delete
}
```

#### 3.4.2 MembreController Updates

```php
// netBS/core/FichierBundle/Controller/MembreController.php

public function removeMembreAction($id, EventDispatcherInterface $dispatcher): Response
{
    $membre = $em->find($this->config->getMembreClass(), $id);

    // Record who deleted the membre
    $currentUser = $this->getUser();
    if ($currentUser instanceof BaseUser) {
        $membre->setDeletedBy($currentUser);
    }

    // Dispatch event for listeners (update to handle soft delete)
    $dispatcher->dispatch(
        new RemoveMembreEvent($membre, $em),
        RemoveMembreEvent::NAME
    );

    // Gedmo handles the soft delete on flush
    $em->flush();

    $this->addFlash('success', 'Membre archivé');
    return $this->redirectToRoute('netbs.core.home.dashboard');
}
```

### Phase 5: Query Updates

#### 3.5.1 Automatic Filtering

The Gedmo SoftDeleteable filter automatically excludes soft-deleted records from all queries. This is already configured in `config/packages/doctrine.yaml`.

#### 3.5.2 Accessing Soft-Deleted Records (Admin)

```php
// To include soft-deleted records in a query:
$em->getFilters()->disable('softdeleteable');
$allUsers = $userRepository->findAll(); // Includes deleted
$em->getFilters()->enable('softdeleteable');

// Or use a custom repository method:
public function findAllIncludingDeleted(): array
{
    return $this->createQueryBuilder('u')
        ->getQuery()
        ->setHint(
            Query::HINT_CUSTOM_OUTPUT_WALKER,
            'Gedmo\SoftDeleteable\Query\TreeWalker\SoftDeleteableWalker'
        )
        ->getResult();
}
```

### Phase 6: Admin Interface

#### 3.6.1 Deleted Records List

Create a new controller and list model to display soft-deleted users and membres:

```php
// New route: /admin/deleted-records
// Displays: Users and Membres with deletedAt IS NOT NULL
// Actions: Restore, Permanent Delete (if GDPR required)
```

#### 3.6.2 Restore Functionality

```php
public function restoreUser(BaseUser $user): void
{
    $user->setDeletedAt(null);
    $user->setDeletedBy(null);
    $this->em->flush();
}

public function restoreMembre(BaseMembre $membre): void
{
    $membre->setDeletedAt(null);
    $membre->setDeletedBy(null);
    $this->em->flush();
}
```

### Phase 7: Audit Trail Fixes

#### 3.7.1 Remove ROLE_SG Exclusion

```php
// netBS/core/CoreBundle/Subscriber/DoctrineLoggerSubscriber.php

protected function shouldLog($object): bool
{
    if (php_sapi_name() === 'cli') {
        return false;
    }

    if (!$this->manager->canRepresent(ClassUtils::getClass($object))) {
        return false;
    }

    // REMOVE THIS CHECK - Log all user actions including admins
    // if ($user->hasRole('ROLE_SG')) {
    //     return false;
    // }

    return true;
}
```

#### 3.7.2 Add User LogRepresenter

```php
// netBS/core/SecureBundle/LogRepresenter/UserLogRepresenter.php

class UserLogRepresenter implements LogRepresenterInterface
{
    public function getEntityClass(): string
    {
        return BaseUser::class;
    }

    public function getTrackedFields(): array
    {
        return ['username', 'email', 'isActive', 'roles', 'deletedAt'];
    }

    // ... implement representation methods
}
```

---

## 4. Database Changes

### 4.1 New Columns

| Table | Column | Type | Nullable | Index |
|-------|--------|------|----------|-------|
| `fos_user` | `deleted_at` | DATETIME | YES | YES |
| `fos_user` | `deleted_by` | INT (FK) | YES | NO |
| `netbs_fichier_membre` | `deleted_at` | DATETIME | YES | YES |
| `netbs_fichier_membre` | `deleted_by` | INT (FK) | YES | NO |

### 4.2 Index Requirements

- Index on `deleted_at` for efficient filtering
- The Gedmo filter adds `WHERE deleted_at IS NULL` to all queries

### 4.3 Foreign Key Considerations

- `deleted_by` references `fos_user.id`
- Must handle circular reference: User deleted_by another User
- Consider: What if the deleting user is later deleted?

---

## 5. Code Changes

### 5.1 Files to Modify

| File | Change |
|------|--------|
| `netBS/core/SecureBundle/Mapping/BaseUser.php` | Add SoftDeleteable annotation and trait |
| `netBS/core/FichierBundle/Mapping/BaseMembre.php` | Add SoftDeleteable annotation and trait |
| `netBS/core/SecureBundle/Service/UserManager.php` | Update deleteUser() logic |
| `netBS/core/FichierBundle/Controller/MembreController.php` | Update removeMembreAction() |
| `netBS/core/SecureBundle/Listener/RemoveMembreListener.php` | Update to soft delete user |
| `netBS/ovesco/FacturationBundle/Listener/RemoveMembreListener.php` | Decide on Facture/Créance handling |
| `netBS/core/CoreBundle/Subscriber/DoctrineLoggerSubscriber.php` | Remove ROLE_SG exclusion |

### 5.2 New Files to Create

| File | Purpose |
|------|---------|
| `config/packages/doctrine_migrations.yaml` | Migration configuration |
| `migrations/VersionXXX_AddSoftDeleteToUser.php` | User table migration |
| `migrations/VersionXXX_AddSoftDeleteToMembre.php` | Membre table migration |
| `netBS/core/SecureBundle/LogRepresenter/UserLogRepresenter.php` | User audit logging |
| `netBS/core/SecureBundle/Controller/DeletedRecordsController.php` | Admin restore interface |
| `netBS/core/SecureBundle/ListModel/DeletedUsersList.php` | Deleted users list |
| `netBS/core/FichierBundle/ListModel/DeletedMembresList.php` | Deleted membres list |

---

## 6. Caveats and Considerations

### 6.1 Critical Caveats

#### 6.1.1 Unique Constraints

**Problem:** If `username` or `email` has a unique constraint, soft-deleted users block new registrations with the same values.

**Solutions:**
1. **Composite unique constraint:** `UNIQUE(username, deleted_at)` - MySQL doesn't support this well with NULL
2. **Suffix on delete:** Append timestamp to username/email on soft delete: `user@example.com` → `user@example.com_deleted_1704067200`
3. **Separate archive table:** Move deleted records to a separate table

**Recommended:** Option 2 - Suffix approach

```php
public function deleteUser(BaseUser $user): void
{
    $timestamp = time();
    $user->setUsername($user->getUsername() . '_deleted_' . $timestamp);
    $user->setEmail($user->getEmail() . '_deleted_' . $timestamp);
    // Then soft delete...
}
```

#### 6.1.2 Related Entity Handling

**Problem:** When a Membre is soft-deleted, what happens to:
- Their `Attribution` records?
- Their `Facture` and `Créance` records?
- Their contact information (Email, Phone, Address)?
- Their associated User account?

**Options:**

| Strategy | Pros | Cons |
|----------|------|------|
| **Cascade soft delete** | Data stays linked | Complex queries, storage growth |
| **Orphan (nullify FK)** | Clean separation | Loses relationship |
| **Keep as-is** | Simple | Queries may return unexpected results |
| **Hard delete related** | Clean | Loses historical data |

**Recommended:**
- `User` ↔ `Membre`: Cascade soft delete (if Membre deleted, soft delete User)
- `Attribution`: Keep as-is (historical record of membership)
- `Facture`/`Créance`: Keep as-is (financial records must be preserved)
- Contact info: Cascade soft delete (personal data)

#### 6.1.3 Authentication of Deleted Users

**Problem:** Soft-deleted users might still have valid sessions or tokens.

**Solution:**
- Check `deletedAt` in the User provider
- Invalidate sessions on soft delete
- Add `deletedAt IS NULL` check to authentication query

```php
// Security/UserProvider.php
public function loadUserByIdentifier(string $identifier): UserInterface
{
    $user = $this->repository->findOneBy([
        'username' => $identifier,
        'deletedAt' => null  // Exclude soft-deleted
    ]);

    if (!$user) {
        throw new UserNotFoundException();
    }

    return $user;
}
```

#### 6.1.4 Performance Impact

**Problem:** The `deleted_at IS NULL` filter is added to ALL queries on soft-deleteable entities.

**Mitigation:**
- Add index on `deleted_at` column
- For large tables, consider partitioning
- Monitor query performance after deployment

#### 6.1.5 Backup and Storage Growth

**Problem:** Soft-deleted records accumulate indefinitely.

**Solution:** Implement a retention policy:
- Archive soft-deleted records older than X years
- Or hard delete after retention period (with proper GDPR consideration)
- Regular cleanup job

### 6.2 Gedmo-Specific Caveats

#### 6.2.1 Filter Scope

The SoftDeleteable filter is **global** - it affects all queries including:
- Admin dashboards (might hide deleted records unintentionally)
- Reports and statistics
- Relationship loading (lazy/eager)

**Solution:** Explicitly disable filter when needed:
```php
$em->getFilters()->disable('softdeleteable');
```

#### 6.2.2 DQL Joins

**Problem:** Joins to soft-deleted entities may return NULL unexpectedly.

```php
// This query might return membres with NULL attributions
// if some attributions were soft-deleted
$qb->select('m', 'a')
   ->from(Membre::class, 'm')
   ->leftJoin('m.attributions', 'a');
```

#### 6.2.3 Cascade Remove in Doctrine

**Problem:** If entity has `cascade={"remove"}` on relationships, Doctrine will hard delete related entities before Gedmo can soft delete.

**Solution:** Remove `cascade={"remove"}` from relationships or handle manually.

### 6.3 Business Logic Caveats

#### 6.3.1 Membre Status vs Soft Delete

**Confusion Risk:** The system already has member statuses (`DESINSCRIT`, `DECEDE`, etc.) which are conceptually similar to soft delete.

**Clarification:**
- `statut = DESINSCRIT`: Member left the organization (logical state, still visible)
- `deletedAt IS NOT NULL`: Record is archived/hidden (technical state, hidden from queries)

**Recommendation:** Document the distinction clearly. Consider whether `DESINSCRIT` membres should also be soft-deleted or remain visible.

#### 6.3.2 Reporting and Statistics

**Problem:** Historical reports may need to include soft-deleted records.

**Solution:**
- Reports should explicitly disable soft delete filter
- Or use dedicated reporting queries that include deleted records
- Add `includeDeleted` parameter to report generators

#### 6.3.3 Data Export (RGPD)

**Problem:** When exporting member data (for RGPD requests), should soft-deleted data be included?

**Solution:** Yes, include soft-deleted data in RGPD exports. The data still exists and belongs to the person.

### 6.4 Migration Caveats

#### 6.4.1 No Existing Migration History

**Problem:** The project has no migration history. First migration will be against an unknown schema state.

**Solution:**
1. Generate baseline migration from current schema
2. Mark it as executed without running
3. Then add soft delete migration

```bash
# Generate baseline
php bin/console doctrine:migrations:dump-schema

# Mark as executed (don't actually run)
php bin/console doctrine:migrations:version --add 'DoctrineMigrations\VersionXXX'
```

#### 6.4.2 Production Deployment

**Problem:** Adding columns to large tables can lock the table.

**Solution:**
- Use `pt-online-schema-change` for MySQL
- Or schedule during maintenance window
- Test migration time on production-sized dataset

---

## 7. Migration Strategy

### 7.1 Pre-Migration Steps

1. **Full database backup**
2. **Test migration on staging** with production data copy
3. **Communicate maintenance window** to users
4. **Prepare rollback scripts**

### 7.2 Migration Execution Order

```bash
# 1. Enable maintenance mode
php bin/console app:maintenance:enable

# 2. Run migrations
php bin/console doctrine:migrations:migrate

# 3. Clear cache
php bin/console cache:clear

# 4. Verify application
php bin/console app:health-check

# 5. Disable maintenance mode
php bin/console app:maintenance:disable
```

### 7.3 Post-Migration Verification

- [ ] Verify `deleted_at` columns exist
- [ ] Verify indexes are created
- [ ] Test user login still works
- [ ] Test membre listing still works
- [ ] Test deletion creates soft delete (not hard delete)
- [ ] Test soft-deleted records are hidden from normal queries
- [ ] Test admin can view soft-deleted records
- [ ] Test restore functionality

---

## 8. Testing Requirements

### 8.1 Unit Tests

```php
// Test soft delete behavior
public function testUserSoftDelete(): void
{
    $user = $this->createUser();
    $this->userManager->deleteUser($user);

    $this->assertNotNull($user->getDeletedAt());
    $this->assertNull($this->userRepository->find($user->getId())); // Filtered out

    // Disable filter to verify record exists
    $this->em->getFilters()->disable('softdeleteable');
    $this->assertNotNull($this->userRepository->find($user->getId()));
}

// Test restore behavior
public function testUserRestore(): void
{
    $user = $this->createAndDeleteUser();
    $this->userManager->restoreUser($user);

    $this->assertNull($user->getDeletedAt());
    $this->assertNotNull($this->userRepository->find($user->getId()));
}

// Test unique constraint handling
public function testDeletedUserUsernameCanBeReused(): void
{
    $user1 = $this->createUser('testuser');
    $this->userManager->deleteUser($user1);

    // Should not throw unique constraint violation
    $user2 = $this->createUser('testuser');
    $this->assertNotNull($user2->getId());
}
```

### 8.2 Integration Tests

- [ ] Full deletion workflow (UI to database)
- [ ] Cascade behavior verification
- [ ] Authentication with deleted user (should fail)
- [ ] Session invalidation on delete
- [ ] Audit trail for soft delete action

### 8.3 Manual Testing Checklist

- [ ] Delete a user via admin interface
- [ ] Verify user cannot login
- [ ] Verify user hidden from user list
- [ ] View deleted users list (admin)
- [ ] Restore a deleted user
- [ ] Verify restored user can login
- [ ] Delete a membre via admin interface
- [ ] Verify associated user is also soft-deleted
- [ ] Verify membre hidden from membre list
- [ ] Verify attributions still visible in history
- [ ] Restore a deleted membre
- [ ] Run existing reports, verify no errors

---

## 9. Rollback Plan

### 9.1 Database Rollback

```bash
# Revert migration
php bin/console doctrine:migrations:migrate prev

# Or specific version
php bin/console doctrine:migrations:migrate 'DoctrineMigrations\VersionXXX'
```

### 9.2 Code Rollback

```bash
# Revert to previous commit
git revert HEAD

# Or reset to specific commit
git reset --hard <commit-hash>
```

### 9.3 Emergency Rollback (No Migration System)

If migrations fail catastrophically:

```sql
-- Remove soft delete columns
ALTER TABLE fos_user DROP COLUMN deleted_at;
ALTER TABLE fos_user DROP COLUMN deleted_by;
ALTER TABLE netbs_fichier_membre DROP COLUMN deleted_at;
ALTER TABLE netbs_fichier_membre DROP COLUMN deleted_by;
```

---

## 10. Timeline Estimate

| Phase | Tasks | Complexity |
|-------|-------|-----------|
| **Phase 1: Infrastructure** | Install migrations bundle, configure | Low |
| **Phase 2: Database** | Create and test migrations | Medium |
| **Phase 3: Entities** | Add annotations, traits, fields | Medium |
| **Phase 4: Services** | Update deletion logic | Medium |
| **Phase 5: Queries** | Verify/fix affected queries | High |
| **Phase 6: Admin UI** | Create restore interface | Medium |
| **Phase 7: Audit** | Fix logging gaps | Low |
| **Phase 8: Testing** | Unit, integration, manual tests | High |
| **Phase 9: Documentation** | Update user/admin docs | Low |
| **Phase 10: Deployment** | Staged rollout | Medium |

---

## Appendix A: Entity Relationship Diagram

```
┌─────────────────┐       ┌─────────────────┐
│     Membre      │       │      User       │
├─────────────────┤       ├─────────────────┤
│ id              │       │ id              │
│ prenom          │       │ username        │
│ nom             │──────►│ email           │
│ statut          │       │ isActive        │
│ deleted_at  [+] │       │ deleted_at  [+] │
│ deleted_by  [+] │       │ deleted_by  [+] │
└────────┬────────┘       └─────────────────┘
         │
         │ 1:N
         ▼
┌─────────────────┐       ┌─────────────────┐
│   Attribution   │       │    Facture      │
├─────────────────┤       ├─────────────────┤
│ id              │       │ id              │
│ membre_id       │       │ membre_id       │
│ dateDebut       │       │ montant         │
│ dateFin         │       │ (keep as-is)    │
│ (keep as-is)    │       └─────────────────┘
└─────────────────┘

[+] = New fields for soft delete
```

---

## Appendix B: Configuration Reference

### doctrine_migrations.yaml

```yaml
doctrine_migrations:
    migrations_paths:
        'DoctrineMigrations': '%kernel.project_dir%/migrations'
    storage:
        table_storage:
            table_name: 'doctrine_migration_versions'
    all_or_nothing: true
    transactional: true
    check_database_platform: true
    organize_migrations: false
    connection: null
    em: null
```

### doctrine.yaml (existing, no changes needed)

```yaml
doctrine:
    orm:
        filters:
            softdeleteable:
                class: Gedmo\SoftDeleteable\Filter\SoftDeleteableFilter
                enabled: true
```

---

## Appendix C: SQL Reference

### Check for soft-deleted records

```sql
-- All soft-deleted users
SELECT * FROM fos_user WHERE deleted_at IS NOT NULL;

-- All soft-deleted membres
SELECT * FROM netbs_fichier_membre WHERE deleted_at IS NOT NULL;

-- Soft-deleted in last 30 days
SELECT * FROM fos_user
WHERE deleted_at IS NOT NULL
AND deleted_at > DATE_SUB(NOW(), INTERVAL 30 DAY);
```

### Hard delete old soft-deleted records (cleanup)

```sql
-- Delete records soft-deleted more than 2 years ago
DELETE FROM fos_user
WHERE deleted_at IS NOT NULL
AND deleted_at < DATE_SUB(NOW(), INTERVAL 2 YEAR);
```

---

## Document History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2024-XX-XX | Claude | Initial draft |

---

## Approval

| Role | Name | Signature | Date |
|------|------|-----------|------|
| Technical Lead | | | |
| Project Manager | | | |
| Database Admin | | | |
