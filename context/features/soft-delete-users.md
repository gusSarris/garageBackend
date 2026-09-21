# Feature: Soft Delete Support for Users (`soft-delete-users`)

## Overview
Introduce soft deletion for `User` accounts (`app_user` table) across the multi-tenant automotive platform.
Instead of permanently purging user rows from PostgreSQL, soft deletion records a `deleted_at` timestamp, deactivates the account (`is_active = false`), wipes pending invitation tokens, invalidates password hashes, and blocks deleting the last active garage owner.
A reusable Doctrine SQL filter (`SoftDeleteFilter`) targeting a `SoftDeletableInterface` marker automatically excludes soft-deleted records from standard queries while allowing explicit administrative audit queries via `include_deleted=true`.

## Prerequisites
- Multi-tenant PostgreSQL database with `app_user` and `garage` tables.
- Existing user management endpoints in `App\Controller\Api\Admin\GarageUserController`.
- `App\Security\UserChecker` configured on the security firewalls.

---

## Specifications

### 1. Entity & Database Schema Changes (`App\Entity\User`)
- **Entity Marker Interface**:
  - Create `App\Doctrine\Contract\SoftDeletableInterface` (marker interface defining soft-deletable contract).
  - `User` implements `SoftDeletableInterface`.
- **Field Addition**:
  - `deletedAt`: `?\DateTimeImmutable` (nullable, mapped to column `deleted_at TIMESTAMP(0) WITHOUT TIME ZONE NULL`).
  - Helper methods:
    - `getDeletedAt(): ?\DateTimeImmutable`
    - `setDeletedAt(?\DateTimeImmutable $deletedAt): static`
    - `isDeleted(): bool` (returns `$this->deletedAt !== null`)
- **PostgreSQL Partial Unique Index Mapped on Entity**:
  - Remove the old plain unique constraint `#[ORM\UniqueConstraint(name: 'uniq_user_email', fields: ['email'])]` on `User`.
  - Add entity-level partial unique index mapping so Doctrine schema tools (`make:migration`, `doctrine:schema:validate`) do not revert it:
    ```php
    #[ORM\UniqueConstraint(
        name: 'uniq_user_email_active',
        columns: ['email'],
        options: ['where' => '(deleted_at IS NULL)'],
    )]
    ```
  - This allows a soft-deleted user's email to be reused for a new active account while strictly protecting active accounts against duplicates.
- **Audit-Trail & Future Relations Architectural Note**:
  - Note: In current schema, `WorkOrder` does not yet hold a direct foreign key to `User`. When `assignedTo` or `createdBy` relations are introduced in future features, Doctrine's `SoftDeleteFilter` will make lazy to-one proxies to soft-deleted users throw `EntityNotFoundException`. Future features adding user foreign keys must account for this (e.g. eager loading, unfiltering, or storing historical snapshots).
- **Other Hard-Delete Paths**:
  - Note: `Garage::$users` has `orphanRemoval: true` and `cascade: ['remove']`. Staff removal must go through soft-delete workflows rather than `$garage->removeUser()`, to prevent accidental cascade hard deletes.
- **Migration**:
  - Generated via `bin/console make:migration` and applied via `bin/console doctrine:migrations:migrate`.

---

### 2. Automated Doctrine SQL Filter (`App\Doctrine\Filter\SoftDeleteFilter`)
- **Implementation**:
  - Implement a custom Doctrine SQL Filter extending `Doctrine\ORM\Query\Filter\SQLFilter`.
  - Check if target entity implements `App\Doctrine\Contract\SoftDeletableInterface`.
  - If target entity does not implement the interface, return empty string `''`.
  - Dynamically resolve the column name: `$targetEntity->getColumnName('deletedAt')` instead of hardcoding `'deleted_at'`.
  - Appends `sprintf('%s.%s IS NULL', $targetTableAlias, $column)`.
- **Configuration** in `config/packages/doctrine.yaml`:
  ```yaml
  doctrine:
      orm:
          filters:
              soft_delete:
                  class: App\Doctrine\Filter\SoftDeleteFilter
                  enabled: true
  ```
- **Scoped Repository Query (`App\Repository\UserRepository`)**:
  - Do NOT toggle the filter globally in the controller. Implement repository method with safe `try / finally`:
    ```php
    public function findByGarage(Garage $garage, bool $includeDeleted = false): array
    {
        $filters = $this->getEntityManager()->getFilters();
        if ($includeDeleted) {
            $filters->disable('soft_delete');
        }
        try {
            return $this->findBy(['garage' => $garage]);
        } finally {
            if ($includeDeleted) {
                $filters->enable('soft_delete');
            }
        }
    }
    ```

---

### 3. Authentication & Security Guard (`App\Security\UserChecker` & `security.yaml`)
- **Firewall Configuration** (`config/packages/security.yaml`):
  - Register `user_checker: App\Security\UserChecker` on **both** `login` and `api` firewalls:
    ```yaml
    firewalls:
        login:
            ...
            user_checker: App\Security\UserChecker
        api:
            pattern: ^/api
            stateless: true
            user_checker: App\Security\UserChecker
            jwt: ~
    ```
  - This ensures an already-issued JWT token cannot be used after an account is deactivated or soft-deleted.
- **UserChecker Defense in Depth**:
  - The `SoftDeleteFilter` hides soft-deleted users at login, so login failure returns 401 ("User not found").
  - Retain `$user->isDeleted()` check in `App\Security\UserChecker` as defense in depth if queries run without the filter enabled.
  - Throws `CustomUserMessageAccountStatusException('Your account has been deactivated or deleted.')`.

---

### 4. Admin API Updates (`App\Controller\Api\Admin\GarageUserController`)
- **`DELETE /api/admin/garages/{garageId}/users/{userId}`**:
  - **Last Owner Guard**:
    - If the user being deleted has `ROLE_GARAGE_ADMIN`, check if there are any other active `ROLE_GARAGE_ADMIN` users in that garage.
    - If this is the last active garage admin, reject with `409 Conflict` and error message `Cannot delete the last active administrator of a garage`.
  - **Already Deleted Guard**:
    - If the user is already soft-deleted (`deletedAt !== null`), return `404 Not Found`.
  - **Sanitization & Soft Deletion**:
    - Set `$user->setDeletedAt(new \DateTimeImmutable());`
    - Set `$user->setIsActive(false);`
    - Invalidate pending invitation: `$user->setInvitationTokenHash(null); $user->setInvitationExpiresAt(null);`
    - Invalidate password hash: replace password hash with an unusable random value (e.g. `$user->setPassword('*');`).
    - Flush changes.
  - Return `200 OK` with JSON `{"message": "User successfully deleted"}`.
- **`GET /api/admin/garages/{garageId}/users`**:
  - Parse boolean query flag: `$includeDeleted = $request->query->getBoolean('include_deleted');`
  - Delegate query to `$userRepository->findByGarage($garage, $includeDeleted);`
  - Response includes `"deletedAt"` (`string|null` in ATOM format) for each user.

---

### 5. Out of Scope
- **Restore Endpoint**: A restore/undelete endpoint (which must validate that the email has not been re-registered by an active user, returning 409 Conflict if so) is deferred to a future task.
- **Purge / Anonymization Job**: A recurring GDPR scheduled command/job to permanently anonymize or purge soft-deleted records past the statutory retention period is deferred to a follow-up feature.

---

## 6. Testing & Verification Plan
Functional tests in `tests/Admin/GarageUserManagementTest.php` and `tests/Auth/UserSoftDeleteTest.php`:
1. `testSoftDeleteUserSetsTimestampAndDeactivates`: `DELETE` sets `deleted_at` timestamp, `is_active = false`, and does not remove the database row.
2. `testSoftDeletedUserExcludedFromUserListByDefault`: Deleted user is not returned in standard `GET /api/admin/garages/{id}/users`.
3. `testIncludeDeletedQueryParamReturnsArchivedUsers`: `GET .../users?include_deleted=true` includes soft-deleted users with `deletedAt` populated.
4. `testSoftDeletedUserCannotLogin`: Attempting `POST /api/login_check` with credentials of a soft-deleted user returns 401 Unauthorized.
5. `testSoftDeletedUserExistingJwtRejected`: A valid JWT issued before deletion is rejected with 401 once the account is soft-deleted.
6. `testDeleteAlreadyDeletedUserReturns404`: Attempting to delete an already soft-deleted user returns 404 Not Found.
7. `testDuplicateActiveEmailStillRejected`: Attempting to register or create two active users with the same email is rejected with 409/422.
8. `testRecreationOfSameEmailAllowedAfterSoftDelete`: Creating a user via `POST /api/admin/garages/{id}/users` with the email of a soft-deleted user succeeds.
9. `testInviteGarageWithSoftDeletedEmailAllowed`: Onboarding a new garage via `POST /api/admin/garages` with a soft-deleted owner's email succeeds.
10. `testSoftDeleteClearsInvitationTokenAndPassword`: Soft-deleting a user sets `invitationTokenHash` and `invitationExpiresAt` to null and invalidates the password hash.
11. `testCannotDeleteLastGarageAdmin`: Attempting to delete the only active `ROLE_GARAGE_ADMIN` in a garage returns 409 Conflict.
12. **Regression Verification**: All existing tests pass except the hard-delete assertions in `GarageUserManagementTest`, which are updated to expect soft delete.
