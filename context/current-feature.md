# Current Feature

- **Feature Name**: none
- **Branch**: main
- **Status**: none
- **Specification**: none

## Goals

None

## Notes

None

## History
- **vehicle-past-service-history** (Completed: 2026-09-24):
  - Implemented `GET /api/garage/vehicles/{id}/history` endpoint in `VehicleController` with strict tenant isolation, 404 for cross-tenant entities, and `ROLE_MECHANIC` access control.
  - Added query method `findServiceHistoryByVehicle` to `WorkOrderRepository` with ordering (`date DESC, createdAt DESC`), limit enforcement, and optional `exclude_active` filtering.
  - Formatted past service history payload according to spec with date, Greek status labels, description, price, odometerKm, notes, partsNotes, and ATOM timestamps.
  - Implemented comprehensive functional test suite in `tests/Garage/VehicleHistoryApiTest.php` (8 tests, 48 assertions). Full test suite passing (194 tests, 1240 assertions).
  - Paired with frontend feature branch `feature/vehicle-past-service-history`.

- **customer-permissions-and-delete-guardrails** (Completed: 2026-09-24):
  - In `CustomerController::delete` (`DELETE /api/garage/customers/{id}`), restricted customer deletion strictly to `ROLE_GARAGE_ADMIN` via `#[IsGranted('ROLE_GARAGE_ADMIN')]`, rejecting mechanics with HTTP 403 Forbidden.
  - Implemented delete guardrail in `CustomerController::delete` checking for existing repair orders (`WorkOrder`) via `WorkOrderRepository::count(['customer' => $customer, 'garage' => $garage])`. Returns HTTP 409 Conflict with code `CUSTOMER_HAS_WORK_ORDERS`, count, and descriptive error message if work orders exist.
  - Retained customer update (`PATCH /api/garage/customers/{id}`) access for `ROLE_MECHANIC` and `ROLE_GARAGE_ADMIN`.
  - Updated and expanded functional test suite in `tests/Garage/CustomerVehicleManagementTest.php` covering mechanic 403 rejection, admin 409 conflict when work orders exist, admin successful deletion without work orders, and cross-tenant isolation. Full test suite passing (186 tests, 1192 assertions).

- **prevent-duplicate-active-vehicle-work-orders** (Completed: 2026-09-23):
  - In `WorkOrderController::create` (`POST /api/garage/work-orders`), added check for active work orders using `WorkOrderRepository::findActiveWorkOrderByVehicle` (`status NOT IN ('delivered', 'cancelled')` and `pickedUpAt IS NULL`), returning HTTP 409 Conflict with error code `VEHICLE_ALREADY_ACTIVE` and active work order payload.
  - Added PostgreSQL partial unique index `uniq_active_work_order_per_vehicle` to `work_order` (`WHERE status NOT IN ('delivered', 'cancelled') AND picked_up_at IS NULL`) and generated/applied migration `Version20260923180148`.
  - In `LookupController::lookup` (`GET /api/garage/lookup`), enriched vehicle format with `hasActiveWorkOrder` and `activeWorkOrder` details.
  - Implemented comprehensive functional tests in `tests/Garage/WorkOrderManagementTest.php` and `tests/Garage/LookupApiTest.php` covering active conflict rejection, scheduled appointment rejection, re-entry allowance after delivered/cancelled, and database race conditions. Full test suite passing (185 tests, 1181 assertions).
  - Paired with frontend feature branch `feature/connect-new-client-with-backend`.

- **guard-all-routes** (Completed: 2026-09-23):
  - Created comprehensive functional security test suite in `tests/RouteProtectionTest.php` (20 tests, 24 assertions).
  - Verified and asserted that all protected API endpoints (`/api/auth/me`, `/api/auth/change-password`, `/api/garage`, `/api/garage/lookup`, `/api/garage/work-orders`, `/api/garage/vehicles`, `/api/garage/customers`, `/api/garage/users`, `/api/admin/garages`) reject unauthenticated requests with HTTP 401 Unauthorized.
  - Enforced RBAC authorization ensuring `ROLE_MECHANIC` is forbidden (HTTP 403) from platform admin endpoints and garage user management endpoints.
  - Verified public access remains intact for login check (`/api/login_check`) and invitation verification (`/api/auth/invitation/{token}`).
  - Verified non-existent routes (`/queue`, `/dashboard`) return 404 Not Found cleanly without data leakage.
  - Documented feature specification in `context/features/guard-all-routes.md`.
  - Paired with frontend feature branch `feature/guard-all-routes`.

- **connect-lookup-with-backend** (Completed: 2026-09-22):
  - Implemented `App\Controller\Api\Garage\LookupController` with authenticated endpoint `GET /api/garage/lookup`.
  - Added smart lookup by phone (`?phone=...`) returning customer details and all associated registered vehicles.
  - Added smart lookup by license plate (`?plate=...`) returning vehicle specifications and associated customer details.
  - Handled flexible normalization (Greek country code `+30`, `0030`, whitespace, hyphens, and case-insensitivity).
  - Enforced tenant isolation scoped to `$user->getGarage()` with `ROLE_MECHANIC` access control.
  - Implemented comprehensive functional test suite in `tests/Garage/LookupApiTest.php` (8 tests, 41 assertions). Full backend test suite passing (160 tests, 1120 assertions).
  - Paired with frontend feature branch `feature/connect-lookup-with-backend`.

- **update-repair-api** (Completed: 2026-09-22):
  - Verified and documented backend API contract for updating repair work orders (`PATCH /api/garage/work-orders/{id}`).
  - Enforced tenant isolation scoped to `$user->getGarage()` and `ROLE_MECHANIC` access control.
  - Supported automatic lifecycle side effects (`checkedInAt`, `completedAt`, `pickedUpAt`, vehicle mileage and last service date).
  - Documented feature specification in `context/features/update-repair-api.md`.
  - Verified functional test suite (`WorkOrderManagementTest`) passing with 13 tests and 75 assertions (full test suite 152 tests, 1079 assertions).
  - Paired with frontend feature branch `feature/update-repair-api`.

- **display-db-data-on-login** (Completed: 2026-09-22):
  - Verified and documented backend API contract for frontend dashboard data display (`GET /api/garage/work-orders`, `GET /api/auth/me`).
  - Formatted and aligned data models for 9 seeded database repairs, 10 customers, and 10 vehicles associated with workshop tenant Sarris Auto Service (`sarr-c@hotmail.com`).
  - Created feature specification in `context/features/display-db-data-on-login.md`.
  - Verified all functional test suites (`WorkOrderManagementTest`, `CorsHeadersTest`) passing (18 tests, 87 assertions).

- **enable-cors** (Completed: 2026-09-22):
  - Installed and registered `nelmio/cors-bundle` via Composer Flex inside Docker container.
  - Configured `CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'` in `.env` to support frontend SPA dev origins (Next.js, Vite, React).
  - Configured `config/packages/nelmio_cors.yaml` with allowed HTTP methods (`GET, OPTIONS, POST, PUT, PATCH, DELETE`), standard headers (`Content-Type, Authorization, Accept, Origin, X-Requested-With`), exposed headers (`Link, Content-Disposition`), and path regex `^/`.
  - Scaffolded and implemented functional test suite in `tests/Cors/CorsHeadersTest.php` (5 tests, 12 assertions) verifying preflight `OPTIONS` on public and protected endpoints, actual requests, authenticated requests, and disallowed origin rejection. Full test suite passing (151 tests, 1047 assertions).

- **user-profile-api** (Completed: 2026-09-22):
  - Implemented DTOs: `App\DTO\Auth\UpdateProfileRequest` (validating optional `fullName`, `email`) and `App\DTO\Auth\ChangePasswordRequest` (validating `currentPassword`, `newPassword` min 10 + NotCompromisedPassword).
  - Updated `App\Controller\Api\AuthController` with endpoints:
    - `PATCH /api/auth/me`: update personal profile details, handling duplicate email conflict with 409 Conflict, and returning updated user profile.
    - `POST /api/auth/change-password`: verify current password via `UserPasswordHasherInterface::isPasswordValid()`, reject identical passwords with 422 Unprocessable Content, hash and save new credentials.
  - Enforced strict identity security: operates strictly on `$this->getUser()` with zero privilege escalation risks (ignoring `roles`, `garage`, and `id` tampering).
  - Implemented comprehensive functional test suite in `tests/Auth/UserProfileTest.php` (12 tests, 50 assertions). Full test suite passing (146 tests, 1035 assertions).

- **tenant-status-listener** (Completed: 2026-09-21):
  - Implemented `App\EventListener\TenantStatusListener` on `KernelEvents::REQUEST` (priority 0) to intercept tenant requests (`^/api/garage`).
  - Enforced workshop active status (`garage.isActive`) and valid subscription states (`trial`, `active`), returning HTTP 403 Forbidden with structured JSON error payload (`WORKSHOP_INACTIVE_OR_EXPIRED`) on deactivated workshops or expired/cancelled/past_due/suspended subscriptions.
  - Allowed unauthenticated requests and `/api/auth/me` to remain accessible for authenticated users to inspect status and display renewal prompts.
  - Enforced bypass for Platform Super Admins (`ROLE_SUPER_ADMIN`) and HTTP 400 Bad Request rejection for non-super-admin accounts without garage context.
  - Implemented comprehensive functional test suite in `tests/Security/TenantStatusListenerTest.php` (11 tests, 74 assertions). Full test suite passing (134 tests, 985 assertions).

- **garage-work-orders-api** (Completed: 2026-09-21):
  - Implemented DTOs: `CreateWorkOrderRequest` and `UpdateWorkOrderRequest` with validations for UUIDs, description, lifecycle status, date formats, price regex, odometer values, and ISO-8601 timestamps.
  - Implemented `searchByGarage` query helper in `WorkOrderRepository` with eager customer and vehicle joins, filtering by status, customer, vehicle, date, date range, and case-insensitive query substring matching.
  - Implemented `App\Controller\Api\Garage\WorkOrderController` with CRUD endpoints (`GET /api/garage/work-orders`, `POST /api/garage/work-orders`, `GET /api/garage/work-orders/{id}`, `PATCH /api/garage/work-orders/{id}`, `DELETE /api/garage/work-orders/{id}`).
  - Implemented repair lifecycle transitions (`checked_in`, `in_progress`, `completed`, `delivered`, `cancelled`), auto-populating timestamps (`checkedInAt`, `completedAt`, `pickedUpAt`) and updating vehicle service history (`lastServiceDate`, `lastServiceMileage`, odometer updates).
  - Enforced strict tenant isolation via `$user->getGarage()`, returning 400 Bad Request for super admin without garage, and 404 Not Found for cross-tenant entities. Protected with `#[IsGranted('ROLE_MECHANIC')]`.
  - Configured `memory_limit` in `phpunit.dist.xml` and implemented comprehensive functional test suite in `tests/Garage/WorkOrderManagementTest.php` (13 tests, 75 assertions). Full test suite passing (123 tests, 911 assertions).
- **garage-customers-vehicles-api** (Completed: 2026-09-21):
  - Implemented DTOs: `CreateCustomerRequest`, `UpdateCustomerRequest`, `CreateVehicleRequest`, `UpdateVehicleRequest` with validations for contact information, UUIDs, mechanical specifications, and `Y-m-d` date formats.
  - Implemented `searchByGarage` query helpers in `CustomerRepository` (case-insensitive substring search on phone, name, company, email) and `VehicleRepository` (case-insensitive search on plate, VIN, make, model, filtering by customer, and `upcoming_kteo` / `upcoming_service` deadline filters).
  - Implemented `App\Controller\Api\Garage\CustomerController` with CRUD endpoints (`GET /api/garage/customers`, `POST /api/garage/customers`, `GET /api/garage/customers/{id}`, `PATCH /api/garage/customers/{id}`, `DELETE /api/garage/customers/{id}`).
  - Implemented `App\Controller\Api\Garage\VehicleController` with CRUD endpoints (`GET /api/garage/vehicles`, `POST /api/garage/vehicles`, `GET /api/garage/vehicles/{id}`, `PATCH /api/garage/vehicles/{id}`, `DELETE /api/garage/vehicles/{id}`), license plate normalization, and customer-tenant binding.
  - Enforced strict tenant isolation via `$user->getGarage()`, returning 400 Bad Request for super admin without garage, and 404 Not Found for cross-tenant entities. Protected with `#[IsGranted('ROLE_MECHANIC')]`.
  - Implemented comprehensive functional test suite in `tests/Garage/CustomerVehicleManagementTest.php` (12 tests, 93 assertions). Full test suite passing (110 tests, 836 assertions).
- **staff-management** (Completed: 2026-09-21):
  - Implemented `App\DTO\Garage\CreateStaffRequest` with strict validation (`email`, `fullName`, `password` min 10 + NotCompromisedPassword, case-normalized `role`).
  - Implemented `App\Controller\Api\Garage\GarageStaffController` with endpoints:
    - `GET /api/garage/users` (list staff for authenticated user's garage, supporting `?include_deleted=true`).
    - `POST /api/garage/users` (provision `ROLE_MECHANIC` or `ROLE_GARAGE_ADMIN` bound to tenant, catching `UniqueConstraintViolationException` on race conditions).
    - `DELETE /api/garage/users/{id}` (tenant-scoped lookup `findOneBy(['id' => $id, 'garage' => $garage])`, soft-delete with credential sanitization, self-deletion guard, admin-on-admin deletion rejection with 403, defense-in-depth last-admin guard with 409).
  - Enforced strict tenant isolation via `$user->getGarage()`, returning 400 for super admin without garage, and 403 for mechanics.
  - Added `countActiveAdminsByGarageExcluding(Garage $garage, User $excludedUser)` helper method to `UserRepository`.
  - Implemented comprehensive functional test suite in `tests/Garage/GarageStaffManagementTest.php` (15 tests, 73 assertions). Full test suite passing (98 tests, 743 assertions).
- **soft-delete-users** (Completed: 2026-09-21):
  - Created marker interface `App\Doctrine\Contract\SoftDeletableInterface`.
  - Added `deletedAt` (`?\DateTimeImmutable`) column and `isDeleted()` helper to `App\Entity\User`.
  - Replaced strict `UNIQUE(email)` with PostgreSQL partial unique constraint `#[ORM\UniqueConstraint(name: 'uniq_user_email_active', columns: ['email'], options: ['where' => '(deleted_at IS NULL)'])]` on `User` entity and executed migration `Version20260921153146`.
  - Implemented `App\Doctrine\Filter\SoftDeleteFilter` dynamically resolving `$targetEntity->getColumnName('deletedAt')` to filter out soft-deleted entities with `deleted_at IS NULL`; registered and enabled by default in `config/packages/doctrine.yaml`.
  - Added `findByGarage(Garage $garage, bool $includeDeleted = false)` with `try / finally` filter handling to `UserRepository` to prevent filter leakage.
  - Registered `App\Security\UserChecker` on both `login` and `api` firewalls in `security.yaml`, enforcing `isDeleted()` and `isActive()` across both `checkPreAuth` and `checkPostAuth` to reject existing JWT tokens immediately upon user deletion.
  - Updated `DELETE /api/admin/garages/{garageId}/users/{userId}` to soft-delete users (`deletedAt = now()`, `isActive = false`), clear pending invitation tokens/expiry, invalidate password hash (`*`), return 404 if already soft-deleted, and guard against deleting the last active `ROLE_GARAGE_ADMIN` in a garage with 409 Conflict.
  - Updated `GET /api/admin/garages/{garageId}/users` to support `?include_deleted=true` and output `deletedAt` ISO timestamp.
  - Updated `tests/Admin/GarageUserManagementTest.php` with soft-delete assertions and added tests for last owner guard, duplicate delete 404, credential wipe, and active duplicate email rejection.
  - Implemented `tests/UserSoftDeleteTest.php` covering login rejection, existing JWT token invalidation, audit listing, email reuse, and onboarding with soft-deleted email. All 83 tests and 670 assertions passing across full test suite.
- **garage-invitation-onboarding** (Completed: 2026-09-21):
  - Enforced CLI-only creation of Platform Super Admins via `App\Command\CreateSuperAdminCommand` (`app:create-super-admin`) with interactive password prompts (`askHidden`), guardrails against duplicates (`UserRepository::countSuperAdmins`), and required `--force` flag.
  - Added `invitationTokenHash` (SHA-256 hex, 64 chars, unique index) and `invitationExpiresAt` fields to `App\Entity\User` with database migration `Version20260921135042`.
  - Implemented `App\Security\UserChecker` registered on the `login` firewall to block pending/inactive accounts (`isActive = false`) from authenticating.
  - Implemented `App\Service\GarageProvisioner` encapsulating atomic workshop and owner creation, raw token generation, token hashing, and `UniqueConstraintViolationException` handling.
  - Configured `%app.frontend_url%` in `services.yaml` and rate limiting for `/api/auth/invitation` in `rate_limiter.yaml`.
  - Updated `POST /api/admin/garages` to issue hashed invites and return activation URL to Super Admin; added `POST /api/admin/garages/{id}/resend-invite` to re-issue and invalidate old tokens; added `app:invite-garage` CLI command.
  - Implemented public endpoints in `App\Controller\Api\Auth\InvitationController`:
    - `GET /api/auth/invitation/{token}` verifying token with uniform 404 response for invalid/expired tokens and `Cache-Control: no-store`.
    - `POST /api/auth/invitation/accept` using `LockMode::PESSIMISTIC_WRITE`, password validation (min 10 + NotCompromisedPassword), activation, and immediate JWT issuance.
  - Implemented comprehensive functional tests in `tests/Auth/GarageInvitationTest.php` covering single super admin force requirement, onboarding, duplicate email protection, verification, expiration, single-use token lock, inactive user login blocking, owner login post-activation, token re-issuance, and super admin role lockdown. All 74 tests and 630 assertions passing across full test suite.
- **admin-garage-user-api** (Completed: 2026-09-21):
  - Implemented `App\DTO\Admin\CreateGarageUserRequest` with email, name, password, and role validation constraints.
  - Implemented `App\Command\CreateGarageUserCommand` (`app:create-garage-user`) for secure console provisioning of garage users with role validation and auto-generated secure password option.
  - Implemented `App\Controller\Api\Admin\GarageUserController` with endpoints:
    - `GET /api/admin/garages/{garageId}/users` (list users for a tenant).
    - `POST /api/admin/garages/{garageId}/users` (create user for a tenant).
    - `DELETE /api/admin/garages/{garageId}/users/{userId}` (delete user from a tenant with tenant isolation check and self-deletion protection).
  - Protected endpoints with `#[IsGranted('ROLE_SUPER_ADMIN')]`.
  - Implemented comprehensive functional tests in `tests/Admin/GarageUserManagementTest.php` covering listing, creation, role validation, duplicate email handling, deletion, tenant isolation, and CLI command execution. All 62 tests and 461 assertions passing across entire test suite.
- **garage-profile-api** (Completed: 2026-09-21):
  - Implemented `App\DTO\Garage\UpdateGarageProfileRequest` mapping request payload with validation constraints (`#[Assert\Email]`, `#[Assert\Length]`) and strictly whitelisting mutable fields (`name`, `email`, `phone`, `address`, `city`, `postalCode`).
  - Implemented `App\Controller\Api\Garage\GarageProfileController` with endpoints:
    - `GET /api/garage` retrieving tenant-scoped profile strictly from `$user->getGarage()`.
    - `PATCH /api/garage` updating editable fields, updating `updatedAt`, and flushing changes, while ignoring read-only/administrative fields (`vatNumber`, `taxOffice`, `subscriptionStatus`, `isActive`, `id`, `createdAt`).
  - Enforced strict tenant isolation and role restrictions (`ROLE_GARAGE_ADMIN`).
  - Implemented comprehensive functional tests in `tests/Garage/GarageProfileTest.php` covering profile retrieval, updating, field protection, mechanic access rejection, garage creation rejection, unauthenticated rejection, invalid email handling, and tenant isolation. Total 45 tests, 399 assertions passing across entire test suite.
- **platform-super-admin** (Completed: 2026-09-20):
  - Made `User::$garage` relation nullable (`JoinColumn(nullable: true)`) to support workshop-agnostic Platform Super Admins.
  - Generated and executed migration `Version20260920151459` across dev and test environments (`ALTER TABLE app_user ALTER garage_id DROP NOT NULL`).
  - Added `ROLE_SUPER_ADMIN` in `config/packages/security.yaml` with role hierarchy (`ROLE_SUPER_ADMIN -> [ROLE_GARAGE_ADMIN, ROLE_ALLOWED_TO_SWITCH]`) and access control (`^/api/admin`).
  - Updated `JWTCreatedListener` and `AuthController::me()` to handle `null` garage context for Super Admins.
  - Implemented `app:create-super-admin` CLI command (`App\Command\CreateSuperAdminCommand`) for bootstrapping platform super admins with hashed credentials.
  - Implemented `App\Controller\Api\Admin\GarageManagementController` with endpoints:
    - `GET /api/admin/garages` returning all workshops with aggregated stats (`userCount`, `customerCount`, `vehicleCount`, `workOrderCount`).
    - `POST /api/admin/garages` onboarding workshops and primary workshop admins in an atomic transaction with `CreateGarageRequest` DTO.
    - `PATCH /api/admin/garages/{id}/subscription` updating subscription tier and active status with `UpdateSubscriptionRequest` DTO.
  - Implemented comprehensive functional tests (`tests/Admin/GarageManagementTest.php`) verifying RBAC, onboarding, subscription updates, and CLI command. All 36 tests and 330 assertions passing across test suite.
- **jwt-authentication-api** (Completed: 2026-09-20):
  - Installed and configured `lexik/jwt-authentication-bundle` via Composer inside Docker container.
  - Generated SSL keypair for JWT signing (`config/jwt/private.pem`, `config/jwt/public.pem`) and secured keypaths via `.gitignore`.
  - Configured `config/packages/security.yaml` with `login` (`/api/login_check`) and `api` (`/api`) firewalls, role hierarchy, and access control.
  - Configured `api_login_check` route in `config/routes.yaml`.
  - Implemented `JWTCreatedListener` on `lexik_jwt_authentication.on_jwt_created` injecting user metadata (`id`, `email`, `fullName`, `roles`) and tenant metadata (`garageId`, `garageName`) into JWT payload.
  - Implemented `GET /api/auth/me` endpoint in `App\Controller\Api\AuthController` returning authenticated user profile and garage context.
  - Implemented functional `WebTestCase` tests (`tests/AuthApiTest.php`) covering login, authentication failure, token payload claims, and `/api/auth/me`. 28 tests, 264 assertions passing across entire test suite.
- **create-messaging-schema** (Completed: 2026-09-20):
  - Scaffolded `Notification` and `SmsTemplate` entities and repositories via MakerBundle with native UUIDv7 primary keys.
  - Implemented `Notification` audit logging for Native Deep Links (`sms:`, `viber:`) and EasySMS API with delivery status, cost, provider tracking, and error messaging.
  - Implemented `SmsTemplate` with workshop customization, dynamic variables, and active state management.
  - Connected `Notification` to `Garage` (`ManyToOne`, CASCADE), `Customer` (`ManyToOne`, CASCADE), `Vehicle` (`ManyToOne`, SET NULL), and `WorkOrder` (`ManyToOne`, SET NULL).
  - Connected `SmsTemplate` to `Garage` (`ManyToOne`, CASCADE).
  - Configured `OneToMany` collections on `Garage`, `Customer`, `Vehicle`, and `WorkOrder`.
  - Added composite indexes: `(garage_id, created_at)`, `(garage_id, type)`, `(customer_id, created_at)`, `(work_order_id)` on notification, and `(garage_id, type)`, `(garage_id, is_active)` on sms_template.
  - Generated and executed migration `Version20260920132426` across dev and test environments.
  - Implemented KernelTestCase integration tests (`tests/MessagingPersistenceTest.php`). Total 23 tests, 235 assertions passing across the test suite.
- **create-users-table** (Completed: 2026-09-20):
  - Installed `symfony/security-bundle` via Flex and configured security password hasher and user provider.
  - Scaffolded `User` entity and repository via MakerBundle with UUIDv7 primary key and database table `app_user`.
  - Implemented `UserInterface` and `PasswordAuthenticatedUserInterface` supporting `ROLE_GARAGE_ADMIN` (owner) and `ROLE_MECHANIC` with security role hierarchy.
  - Connected `User` with `ManyToOne` relation to `Garage` (tenant) with `onDelete: 'CASCADE'`.
  - Configured `OneToMany` collection on `Garage` with `cascade: ['remove']` and `orphanRemoval: true`.
  - Added composite indexes: `UNIQUE (email)`, `(garage_id)`, and `(garage_id, is_active)`.
  - Generated and executed migration `Version20260920125157` across dev and test environments.
  - Implemented KernelTestCase integration tests (`tests/UserPersistenceTest.php`). Total 17 tests, 161 assertions passing across the test suite.
- **create-work-orders-table** (Completed: 2026-09-20):
  - Scaffolded `WorkOrder` entity and repository via MakerBundle with UUIDv7 primary key.
  - Connected `WorkOrder` with `ManyToOne` relations to `Garage`, `Customer`, and `Vehicle` with `onDelete: 'CASCADE'`.
  - Configured `OneToMany` collections on `Garage`, `Customer`, and `Vehicle` with `cascade: ['remove']` and `orphanRemoval: true`.
  - Added SMS/notification lifecycle fields (`date`, `status`, `scheduledTime`, `description`, `price`, `odometerKm`, `notes`, `partsNotes`, `checkedInAt`, `completedAt`, `pickedUpAt`).
  - Added composite indexes: `(garage_id, status)`, `(vehicle_id, date)`, `(customer_id, date)`, `(garage_id, date)`.
  - Generated and executed migration `Version20260920102321` across dev and test environments.
  - Implemented KernelTestCase integration tests (`tests/WorkOrderPersistenceTest.php`). Total 13 tests, 130 assertions passing across the test suite.
- **create-vehicles-table** (Completed: 2026-09-20):
  - Scaffolded `Vehicle` entity and repository via MakerBundle with UUIDv7 primary key.
  - Connected `Vehicle` with `ManyToOne` to `Garage` and `Customer` with `onDelete: 'CASCADE'`.
  - Configured `OneToMany` relations in `Garage` and `Customer` with `cascade: ['remove']` and `orphanRemoval: true`.
  - Added mechanical attributes and service/KTEO reminder tracking fields (`lastServiceDate`, `lastServiceMileage`, `nextKteoDate`, `nextServiceDate`, `nextServiceMileage`, `lastKteoReminderSentAt`, `lastServiceReminderSentAt`, `allowReminders`).
  - Added composite indexes: `(garage_id, license_plate)`, `(garage_id, vin)`, `(garage_id, next_kteo_date)`, `(garage_id, next_service_date)`, `(customer_id)`, `(garage_id, created_at)`.
  - Generated and executed migration `Version20260920075243` across dev and test environments.
  - Implemented KernelTestCase integration tests (`tests/VehiclePersistenceTest.php`). Total 7 tests, 67 assertions passing.
- **create-garages-and-customers-tables** (Completed: 2026-09-20):
  - Added `symfony/uid` via Flex for native UUIDv7 support.
  - Scaffolded `Garage` and `Customer` entities and repositories via MakerBundle with UUIDv7 primary keys.
  - Configured `ManyToOne` (Customer -> Garage) and `OneToMany` (Garage -> Customer) with `orphanRemoval: true` and cascade remove.
  - Added composite indexes on Customer table: `(garage_id, phone)`, `(garage_id, last_name)`, `(garage_id, created_at)`.
  - Generated and executed migration `Version20260920060134`.
  - Implemented persistence integration tests (`tests/GarageCustomerPersistenceTest.php`) verifying UUIDv7, B2B fields, and cascade deletion. 3 tests, 26 assertions passing.
