# Feature: Platform Super Admin (Approach 1: Nullable Garage)

## Overview
Implement a **Platform Super Admin** (`ROLE_SUPER_ADMIN`) architecture on the Symfony Docker backend.
In this approach, the Platform Super Admin is a top-level administrative user not bound to any individual workshop (`app_user.garage_id` is `NULL`). This user can onboard new garage tenants, manage workshop subscription statuses, view cross-platform metrics, and access administrative endpoints protected by role-based access control.

## Prerequisites
- PostgreSQL multi-tenant schema with `Garage`, `User`, `Customer`, `Vehicle`, `WorkOrder`.
- Stateless JWT authentication bundle (`lexik/jwt-authentication-bundle`) with claims listener and `/api/auth/me`.

## Specifications

### 1. Database Schema & Entity Updates
- **Entity**: `App\Entity\User`
  - Update `$garage` relation:
    ```php
    #[ORM\ManyToOne(inversedBy: 'users')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Garage $garage = null;
    ```
  - Constructor & getters/setters maintain support for nullable garage.
  - Helper method `isSuperAdmin(): bool` checking `in_array('ROLE_SUPER_ADMIN', $this->getRoles(), true)`.
- **Doctrine Migration**:
  - Generate and execute migration altering `app_user`:
    `ALTER TABLE app_user ALTER COLUMN garage_id DROP NOT NULL;`

### 2. Security Configuration (`config/packages/security.yaml`)
- Update `role_hierarchy`:
  ```yaml
  role_hierarchy:
      ROLE_SUPER_ADMIN: [ROLE_GARAGE_ADMIN, ROLE_ALLOWED_TO_SWITCH]
      ROLE_GARAGE_ADMIN: [ROLE_MECHANIC]
  ```
- Protect `/api/admin` routes:
  - Add access control rule in `security.yaml`:
    `- { path: ^/api/admin, roles: ROLE_SUPER_ADMIN }`
  - Enforce `#[IsGranted('ROLE_SUPER_ADMIN')]` on administrative controllers.

### 3. JWT Claims & Current User Adaptation
- **JWT Listener** (`App\EventListener\JWTCreatedListener`):
  - When `$user->getGarage() === null`:
    - `garageId: null`
    - `garageName: null`
  - Claims contain `roles: ['ROLE_SUPER_ADMIN', ...]`.
- **Current User Endpoint** (`GET /api/auth/me`):
  - When `$user->getGarage() === null`:
    - Returns `"garage": null`.

### 4. CLI Super Admin Provisioning Command
- **Command**: `App\Command\CreateSuperAdminCommand` (`app:create-super-admin`)
- Allows quick, secure bootstrapping of platform super admins via CLI:
  ```bash
  docker compose exec -T php bin/console app:create-super-admin admin@clickdrive.io "SecurePass123!" "Platform Administrator"
  ```

### 5. Administrative Endpoints (`App\Controller\Api\Admin\GarageManagementController`)
- **Base Route**: `#[Route('/api/admin/garages', name: 'api_admin_garages_')]`
- **Security**: `#[IsGranted('ROLE_SUPER_ADMIN')]`

#### Endpoints:
1. `GET /api/admin/garages`:
   - Lists all tenant garages with aggregated counts:
     - `id`, `name`, `email`, `phone`, `vatNumber`, `city`
     - `subscriptionStatus`, `isActive`, `createdAt`
     - `stats`: `userCount`, `customerCount`, `vehicleCount`, `workOrderCount`
2. `POST /api/admin/garages`:
   - Onboard a new garage tenant and provision its primary workshop admin (`ROLE_GARAGE_ADMIN`) in a single atomic transaction.
   - **Request Body DTO** (`CreateGarageRequest`):
     - `name` (`string`, required)
     - `email` (`string`, required, email)
     - `phone` (`?string`, optional)
     - `vatNumber` (`?string`, optional)
     - `address` (`?string`, optional)
     - `city` (`?string`, optional)
     - `postalCode` (`?string`, optional)
     - `adminEmail` (`string`, required, email)
     - `adminFullName` (`string`, required)
     - `adminPassword` (`string`, required, min 8)
   - **Response**: `201 Created` with created garage entity and admin user summary.
3. `PATCH /api/admin/garages/{id}/subscription`:
   - Update garage subscription tier and active flag.
   - **Request Body DTO** (`UpdateSubscriptionRequest`):
     - `subscriptionStatus` (`string`, choice: `'trial'`, `'active'`, `'past_due'`, `'cancelled'`)
     - `isActive` (`?bool`, optional)
   - **Response**: `200 OK` with updated garage entity.

### 6. Automated Testing & Verification
Functional tests (`WebTestCase`) in `tests/Admin/GarageManagementTest.php`:
- `testSuperAdminCanListGarages`: `ROLE_SUPER_ADMIN` gets 200 and list with stats.
- `testGarageAdminForbiddenFromPlatformEndpoints`: `ROLE_GARAGE_ADMIN` gets 403 Forbidden.
- `testMechanicForbiddenFromPlatformEndpoints`: `ROLE_MECHANIC` gets 403 Forbidden.
- `testUnauthenticatedForbiddenFromPlatformEndpoints`: Unauthenticated call gets 401 Unauthorized.
- `testSuperAdminCanOnboardNewGarage`: Successfully creates garage and tenant admin.
- `testSuperAdminCanUpdateSubscription`: Successfully updates subscription status and active flag.
- `testAuthMeForSuperAdmin`: `/api/auth/me` returns `garage: null` and `ROLE_SUPER_ADMIN`.
- `testCreateSuperAdminCommand`: CLI command successfully creates a user with `garage = null` and `ROLE_SUPER_ADMIN`.
