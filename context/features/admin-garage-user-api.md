# Feature: Admin Garage User API & CLI (`admin-garage-user-api`)

## Overview
Implement Super Admin management for workshop users (both `ROLE_GARAGE_ADMIN` and `ROLE_MECHANIC`) across tenants.
This feature empowers Platform Super Admins to inspect, provision, and delete garage users both via authenticated administrative REST API endpoints and via a dedicated, secure Symfony CLI command.

## Prerequisites
- PostgreSQL multi-tenant schema with `Garage` and `User` (`app_user`).
- Role hierarchy: `ROLE_SUPER_ADMIN: [ROLE_GARAGE_ADMIN, ROLE_ALLOWED_TO_SWITCH]`.
- Existing `#[IsGranted('ROLE_SUPER_ADMIN')]` administrative firewall on `^/api/admin`.

## Specifications

### 1. Administrative Endpoints (`App\Controller\Api\Admin\GarageUserController`)

- **Base Route**: `#[Route('/api/admin/garages/{garageId}/users', name: 'api_admin_garage_users_')]`
- **Security**: `#[IsGranted('ROLE_SUPER_ADMIN')]`

#### Endpoints:

1. **`GET /api/admin/garages/{garageId}/users`** (`name: 'list'`)
   - **Behavior**: Retrieves all users belonging to the specified garage tenant.
   - **Validation**: If `garageId` does not exist, returns `404 Not Found`.
   - **Response**: `200 OK`
     ```json
     [
       {
         "id": "019213ab-...",
         "email": "nikos@service.gr",
         "fullName": "Nikos Papadopoulos",
         "roles": ["ROLE_MECHANIC"],
         "isActive": true,
         "createdAt": "2026-09-21T12:00:00+00:00"
       }
     ]
     ```

2. **`POST /api/admin/garages/{garageId}/users`** (`name: 'create'`)
   - **Behavior**: Creates a new user tied to the specified garage tenant.
   - **Validation**:
     - `garageId` must exist (returns `404 Not Found` if missing).
     - Email must be unique across `app_user` (returns `409 Conflict` if duplicate).
     - Request body mapped to `App\DTO\Admin\CreateGarageUserRequest`:
       - `email` (`string`, required, valid email format)
       - `fullName` (`string`, required, min 2, max 255)
       - `password` (`string`, required, min 8)
       - `role` (`string`, choice: `'admin'` -> `ROLE_GARAGE_ADMIN`, `'mechanic'` -> `ROLE_MECHANIC`)
   - **Response**: `201 Created` with created user details (excluding password hash).

3. **`DELETE /api/admin/garages/{garageId}/users/{userId}`** (`name: 'delete'`)
   - **Behavior**: Deletes/removes the specified user from the specified garage.
   - **Validation**:
     - `garageId` must exist (returns `404 Not Found`).
     - `userId` must exist and belong to the specified garage (returns `404 Not Found` if not found or if the user belongs to a different tenant).
     - Protection: Prevent deleting the current authenticated user (if a super admin targets their own account) or ensure safe handling.
   - **Response**: `204 No Content` or `200 OK` with confirmation message.

---

### 2. CLI Provisioning Command (`App\Command\CreateGarageUserCommand`)

- **Command**: `app:create-garage-user`
- **Description**: Securely creates a garage user for a specified garage tenant from the console without exposing credentials over HTTP.
- **Arguments / Options**:
  - `garageIdentifier` (UUID or garage email, required argument)
  - `email` (user email, required argument)
  - `fullName` (user full name, required argument)
  - `role` (user role: `admin` or `mechanic`, defaults to `mechanic`)
  - `--password` (optional password; if omitted, a cryptographically secure 16-character temporary password is auto-generated and displayed once)
- **Error Handling**:
  - Validates role against allowed choices (`admin`, `mechanic`).
  - Validates garage existence.
  - Rejects duplicate email addresses.

---

### 3. Request DTOs
- `App\DTO\Admin\CreateGarageUserRequest`:
  - `#[Assert\NotBlank]` `#[Assert\Email]` `public string $email;`
  - `#[Assert\NotBlank]` `#[Assert\Length(min: 2, max: 255)]` `public string $fullName;`
  - `#[Assert\NotBlank]` `#[Assert\Length(min: 8)]` `public string $password;`
  - `#[Assert\NotBlank]` `#[Assert\Choice(choices: ['admin', 'mechanic', 'ROLE_GARAGE_ADMIN', 'ROLE_MECHANIC'])]` `public string $role = 'mechanic';`

---

### 4. Testing & Verification
Functional tests (`WebTestCase` and `KernelTestCase`) in `tests/Admin/GarageUserManagementTest.php`:
- `testSuperAdminCanListGarageUsers`: Super Admin gets 200 and list of users for existing garage.
- `testListGarageUsersNotFoundForInvalidGarage`: Returns 404 for invalid garage ID.
- `testSuperAdminCanCreateGarageUser`: Super Admin successfully creates a mechanic and an admin.
- `testCreateGarageUserFailsOnDuplicateEmail`: Returns 409 Conflict.
- `testCreateGarageUserFailsOnInvalidRole`: Returns 422 Unprocessable Content.
- `testSuperAdminCanDeleteGarageUser`: Super Admin successfully deletes a user.
- `testDeleteGarageUserFailsIfUserBelongsToDifferentGarage`: Returns 404 (tenant isolation check).
- `testGarageAdminForbiddenFromAdminUserEndpoints`: Returns 403 Forbidden.
- `testMechanicForbiddenFromAdminUserEndpoints`: Returns 403 Forbidden.
- `testUnauthenticatedForbiddenFromAdminUserEndpoints`: Returns 401 Unauthorized.
- `testCreateGarageUserCommand`: Verifies CLI command execution, auto-generated password option, and persistence in the database.
