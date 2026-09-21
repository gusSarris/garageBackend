# Feature: Garage Staff Management API (`staff-management`)

## Overview
Enable workshop administrators (`ROLE_GARAGE_ADMIN`) to autonomously manage staff members (primarily mechanics `ROLE_MECHANIC`) within their own workshop tenant.
This feature eliminates the bottleneck where workshop owners had to rely on Platform Super Admins to provision or remove staff accounts.

## Security & Multi-Tenant Isolation Guarantees

1. **Strict Tenant Context Scoping (Zero Cross-Tenant Leakage)**:
   - The endpoints under `/api/garage/users` do **NOT** accept a `{garageId}` parameter in the URL.
   - The workshop context is resolved exclusively from the authenticated user: `$user->getGarage()`.
   - On queries (e.g. `DELETE /api/garage/users/{id}`), the target entity is looked up strictly scoped by tenant: `findOneBy(['id' => $id, 'garage' => $garage])`.
   - Cross-tenant targets, non-existent users, and already soft-deleted users all uniformly resolve to `404 Not Found`, revealing zero information about other tenants.
2. **Access Control**:
   - Controller protected with `#[IsGranted('ROLE_GARAGE_ADMIN')]`.
   - Mechanics (`ROLE_MECHANIC`) receive `403 Forbidden` if attempting to list, create, or delete staff.
   - Unauthenticated requests receive `401 Unauthorized`.
   - Platform Super Admins without a linked garage (`garage = null`) receive `400 Bad Request` if attempting to invoke tenant-scoped staff management.
3. **Safety Guardrails & Policy Decisions**:
   - **Self-Deletion Guard**: A garage admin cannot delete their own authenticated account via this endpoint (`400 Bad Request`).
   - **Admin-on-Admin Deletion Protection (Decision)**:
     - To prevent rogue co-administrators from deleting the founding workshop owner, `DELETE /api/garage/users/{id}` is restricted to deleting staff holding `ROLE_MECHANIC`.
     - Attempting to delete a user holding `ROLE_GARAGE_ADMIN` returns `403 Forbidden` (Administrative user removal requires Super Admin escalation).
   - **Last Admin Guard (Defense-in-Depth)**:
     - Condition: "Active admins in garage excluding target <= 0 → `409 Conflict`".
     - Ensures that no operation can ever leave a workshop with zero active administrators.
   - **Soft Deletion & Immediate Token Invalidation**:
     - Deleting a staff member performs a soft delete (`deletedAt = now()`, `isActive = false`).
     - Clears sensitive credentials (`password = '*'`, clears invitation tokens).
     - Because `UserChecker` checks `isDeleted()` and `isActive()`, any existing JWT token for that staff member is immediately revoked (subsequent requests fail with `401 Unauthorized`).
     - Thanks to PostgreSQL partial unique constraint `(email) WHERE (deleted_at IS NULL)`, a soft-deleted staff member's email can be immediately reused if re-hired or re-registered.
   - **Role Escalation Protection**:
     - Garage owners can provision staff members with `ROLE_MECHANIC` (default) or `ROLE_GARAGE_ADMIN` (co-owner/manager).
     - Under no circumstances can a garage owner grant `ROLE_SUPER_ADMIN` (`422 Unprocessable Content`).
   - **Duplicate Email & Concurrency Protection**:
     - System performs an initial lookup for existing active email, returning `409 Conflict`.
     - Also catches `UniqueConstraintViolationException` on `flush()` and transforms it to `409 Conflict` to safely handle race conditions.
     - Note: Global uniqueness across `app_user` is an intentional platform design constraint due to unified JWT email login.

---

## Specifications

### Base Route: `#[Route('/api/garage/users', name: 'api_garage_users_')]`
**Security**: `#[IsGranted('ROLE_GARAGE_ADMIN')]`
**Controller**: `App\Controller\Api\Garage\GarageStaffController`

---

### 1. `GET /api/garage/users`
- **Route Name**: `list`
- **Method**: `GET`
- **Query Parameters**:
  - `include_deleted` (`bool`, optional, default: `false`): If `true`, returns soft-deleted staff members for auditing.
- **Behavior**:
  - Resolves `$garage = $user->getGarage()`. Returns `400 Bad Request` if `$garage === null`.
  - Queries staff users using `UserRepository::findByGarage($garage, $includeDeleted)`.
- **Response**: `200 OK`
  ```json
  [
    {
      "id": "019213ab-...",
      "email": "mechanic@garage.gr",
      "fullName": "Giorgos Oikonomou",
      "roles": ["ROLE_MECHANIC"],
      "isActive": true,
      "createdAt": "2026-09-21T10:00:00+00:00",
      "deletedAt": null
    }
  ]
  ```

---

### 2. `POST /api/garage/users`
- **Route Name**: `create`
- **Method**: `POST`
- **Request Payload DTO**: `App\DTO\Garage\CreateStaffRequest`
  - `email` (`string`, required, valid email format)
  - `fullName` (`string`, required, min 2, max 255)
  - `password` (`string`, required, min 10, max 4096, `#[Assert\NotCompromisedPassword]`)
  - `role` (`string`, optional, default: `'mechanic'`, allowed: `['mechanic', 'admin', 'ROLE_MECHANIC', 'ROLE_GARAGE_ADMIN']`)
- **Behavior**:
  - Validates payload via `#[MapRequestPayload]`.
  - Checks if active user with `email` already exists; if so, returns `409 Conflict`.
  - Maps normalized role:
    - `'mechanic'` / `'role_mechanic'` -> `ROLE_MECHANIC`
    - `'admin'` / `'role_garage_admin'` -> `ROLE_GARAGE_ADMIN`
  - Instantiates `User`, associates with `$garage = $user->getGarage()`, sets hashed password, sets `isActive = true`.
  - Persists and flushes inside `try / catch (UniqueConstraintViolationException) -> 409 Conflict`.
- **Response**: `201 Created`
  ```json
  {
    "id": "019213ab-...",
    "email": "mechanic@garage.gr",
    "fullName": "Giorgos Oikonomou",
    "roles": ["ROLE_MECHANIC"],
    "isActive": true,
    "createdAt": "2026-09-21T10:00:00+00:00"
  }
  ```

---

### 3. `DELETE /api/garage/users/{id}`
- **Route Name**: `delete`
- **Method**: `DELETE`
- **Behavior**:
  - Resolves `$garage = $currentUser->getGarage()`. Returns `400 Bad Request` if `$garage === null`.
  - Scoped query: `findOneBy(['id' => $id, 'garage' => $garage])`.
  - If user is null or already soft-deleted (`$user->isDeleted()`), returns `404 Not Found`.
  - **Self-Deletion Guard**: If `$targetUser->getId()?->toRfc4122() === $currentUser->getId()?->toRfc4122()`, returns `400 Bad Request`.
  - **Admin-on-Admin Protection**: If target user has `ROLE_GARAGE_ADMIN`, returns `403 Forbidden` ("Cannot delete a workshop administrator. Contact platform support.").
  - **Last Admin Guard (Defense-in-Depth)**: If target is admin and active admins excluding target <= 0, returns `409 Conflict`.
  - Soft-deletes:
    - Sets `deletedAt = new \DateTimeImmutable()`
    - Sets `isActive = false`
    - Sets `password = '*'`
    - Clears `invitationTokenHash = null` and `invitationExpiresAt = null`
  - Flushes changes.
- **Response**: `200 OK`
  ```json
  {
    "message": "Staff member successfully deleted"
  }
  ```

---

## Testing Plan (`tests/Garage/GarageStaffManagementTest.php`)

1. `testGarageAdminCanListStaff`: Owner lists staff belonging to their garage, and asserts another garage's users are absent.
2. `testGarageAdminCanCreateMechanic`: Owner successfully provisions a mechanic with `ROLE_MECHANIC`.
3. `testGarageAdminCanCreateCoAdmin`: Owner successfully provisions a co-administrator with `ROLE_GARAGE_ADMIN`.
4. `testCreateStaffFailsOnDuplicateEmail`: Rejects existing active user email with `409 Conflict`.
5. `testCreateStaffRejectsSuperAdminRole`: Rejects request attempting to assign `ROLE_SUPER_ADMIN` or invalid roles with `422 Unprocessable Content`.
6. `testCreateStaffEnforcesPasswordPolicy`: Rejects password shorter than 10 characters with `422 Unprocessable Content`.
7. `testGarageAdminCanSoftDeleteStaff`: Owner soft-deletes mechanic; credentials wiped (`password = '*'`); `deletedAt` recorded; old JWT returns `401 Unauthorized`; re-creating a user with the same email succeeds.
8. `testDeleteStaffFailsForCrossTenant`: Cannot delete staff belonging to a different garage (`404 Not Found` via tenant scoping).
9. `testDeleteStaffFailsOnSelfDeletion`: Owner attempting to delete themselves returns `400 Bad Request`.
10. `testDeleteStaffFailsWhenTargetIsAdmin`: Garage admin attempting to delete another `ROLE_GARAGE_ADMIN` returns `403 Forbidden`.
11. `testDeleteAlreadyDeletedUserReturns404`: Attempting to delete an already soft-deleted staff member returns `404 Not Found`.
12. `testSuperAdminWithoutGarageReturnsBadRequest`: Super admin with `garage = null` calling `/api/garage/users` endpoints returns `400 Bad Request`.
13. `testMechanicForbiddenFromManagingStaff`: Mechanic receives `403 Forbidden` on staff management endpoints.
14. `testUnauthenticatedForbidden`: Unauthenticated requests receive `401 Unauthorized`.
15. `testListStaffSupportsIncludeDeleted`: Soft-deleted staff appear when `?include_deleted=true` is requested.
