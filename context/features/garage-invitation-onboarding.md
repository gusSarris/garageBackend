# Feature: Garage Owner Invitation Onboarding & CLI-Only Super Admin

## Overview
Implement the B2B sales-to-onboarding workflow for workshop owners and enforce CLI-only creation of Platform Super Admins with interactive passwords and `--force` guardrails.

When the platform owner closes a sale with a garage owner, the Super Admin provisions the workshop via a unified `GarageProvisioner` service (accessible via `POST /api/admin/garages` or CLI). An invitation token is generated, hashed with SHA-256 in the database, and returned as a full activation URL to the Super Admin (who manually delivers it to the owner). The garage owner opens the link, verifies their invitation, sets a secure password, and is automatically authenticated with full workshop admin privileges (`ROLE_GARAGE_ADMIN`).

---

## Specifications

### 1. Super Admin Creation Is CLI-Only
- `ROLE_SUPER_ADMIN` is granted **only** by `app:create-super-admin`. No HTTP endpoint may set it. User roles in onboarding are hardcoded in `GarageProvisioner`, never read from HTTP request payloads.
- **Interactive Password**: Passwords for Super Admin are prompted interactively via `$io->askHidden('Password')`, never accepted as CLI command arguments.
- **Guardrail**: If a super admin already exists in the database, running `app:create-super-admin` aborts with an error unless `--force` is provided. When `--force` is passed, it prints a warning and logs the override.

---

### 2. Schema Updates: Hashed Invitation Token on User
- **Entity**: `App\Entity\User`
  - Add fields:
    - `invitationTokenHash`: `?string` (length: 64, unique: true, nullable: true). Stores SHA-256 hex hash of the raw token.
    - `invitationExpiresAt`: `?\DateTimeImmutable` (nullable: true).
- **Security & Hashing**:
  - Raw token: `$raw = bin2hex(random_bytes(32))` (64 characters).
  - Stored token: `hash('sha256', $raw)` in `app_user.invitation_token_hash`.
  - The raw token is returned **only once** upon invite creation/resend and is never stored in plaintext or logged.
- **Migration**:
  - Generate and run migration adding `invitation_token_hash` (unique index) and `invitation_expires_at` to `app_user`.

---

### 3. Account Activation Protection (`UserChecker`)
- Pending users (`isActive = false`) must not be able to authenticate.
- Implement `App\Security\UserChecker` implementing `Symfony\Component\Security\Core\User\UserCheckerInterface`:
  - `checkPreAuth(UserInterface $user)`: verifies `$user->isActive()`.
  - If inactive, throws `CustomUserMessageAccountStatusException('Account is pending activation or has been disabled.')`.
- Register `App\Security\UserChecker` on the `login` firewall in `config/packages/security.yaml`.

---

### 4. Unified Workshop Provisioning (`GarageProvisioner`)
- **Service**: `App\Service\GarageProvisioner`
  - Encapsulates atomic creation of `Garage` and owner `User`.
  - Hardcodes owner role to `ROLE_GARAGE_ADMIN`.
  - Sets owner `isActive: false` and generates a secure random password placeholder.
  - Generates raw token and persists `invitationTokenHash` with 7-day expiration (`new \DateTimeImmutable('+7 days')`).
  - Wraps in a database transaction and handles `UniqueConstraintViolationException` by throwing a domain exception or returning HTTP 409/422.
  - Constructs invite URL using configured base `%app.frontend_url%` (e.g. `https://app.clickdrive.io/activate?token={rawToken}`).
- **Endpoint**: `POST /api/admin/garages`
  - Replaces old direct-password creation with the invitation flow.
  - **Security**: `#[IsGranted('ROLE_SUPER_ADMIN')]`
  - **Request Body DTO** (`CreateGarageRequest`):
    - `name` (`string`, required)
    - `email` (`string`, required, email - Garage contact email)
    - `phone` (`?string`, optional)
    - `vatNumber` (`?string`, optional)
    - `address` (`?string`, optional)
    - `city` (`?string`, optional)
    - `postalCode` (`?string`, optional)
    - `ownerEmail` (`string`, required, email)
    - `ownerFullName` (`string`, required)
  - **Response**: `201 Created` with garage summary, owner summary, `invitationUrl`, and `expiresAt`.
- **Endpoint**: `POST /api/admin/garages/{id}/resend-invite`
  - **Security**: `#[IsGranted('ROLE_SUPER_ADMIN')]`
  - Finds owner user for the garage.
  - Generates a new raw token, computes new SHA-256 hash, and updates `invitationExpiresAt` (+7 days), invalidating the old token.
  - Returns `200 OK` with new `invitationUrl` and `expiresAt`.
- **CLI Command**: `App\Command\InviteGarageCommand` (`app:invite-garage`)
  - Calls `GarageProvisioner` directly to provision a workshop and output the invitation URL.

---

### 5. Public Invitation Verification & Password Acceptance
- **Security / Routing**:
  - In `config/packages/security.yaml`, place `- { path: ^/api/auth/invitation, roles: PUBLIC_ACCESS }` **before** `- { path: ^/api, roles: IS_AUTHENTICATED_FULLY }`.
  - Apply Rate Limiting to `/api/auth/invitation` per client IP.

#### 5.1. Verify Invitation Token
- **Route**: `GET /api/auth/invitation/{token}`
- **Security & Privacy**:
  - Hashes incoming token: `$tokenHash = hash('sha256', $token)`.
  - Looks up user by `invitationTokenHash`.
  - If user not found, or `invitationExpiresAt < new \DateTimeImmutable()`, or user is already active:
    - Returns **`404 Not Found`** with identical body `{"error": "Invalid or expired invitation token"}` to prevent status enumeration.
  - Header: `Cache-Control: no-store` (tokens must never be cached by intermediaries).
- **Response**: `200 OK`
  ```json
  {
    "email": "owner@garage.example.com",
    "fullName": "Γιώργος Παπαδόπουλος",
    "garage": {
      "name": "Auto Service Center",
      "city": "Athens"
    }
  }
  ```

#### 5.2. Accept Invitation & Complete Registration
- **Route**: `POST /api/auth/invitation/accept`
- **Request Body DTO** (`AcceptInvitationRequest`):
  - `token` (`string`, required)
  - `password` (`string`, required, min: 10, `#[Assert\NotCompromisedPassword]`)
  - `fullName` (`?string`, optional)
- **Concurrency & Atomicity**:
  - Hashes incoming token: `$tokenHash = hash('sha256', $token)`.
  - Inside a database transaction, loads user with `LockMode::PESSIMISTIC_WRITE`.
  - Verifies token validity and expiration. If invalid, aborts with `404 Not Found`.
  - Sets new hashed password (`UserPasswordHasherInterface`).
  - Sets `isActive = true`.
  - Invalidates token: `invitationTokenHash = null`, `invitationExpiresAt = null`.
  - Commits transaction.
  - Issues and returns a JWT token via `JWTTokenManagerInterface` so owner is immediately logged in.
- **Response**: `200 OK` with `token`, user profile, and garage profile.

---

### 6. Automated Testing & Verification
Functional tests in `tests/Auth/GarageInvitationTest.php` and `tests/Admin/GarageManagementTest.php`:
1. `testSecondSuperAdminRequiresForce`: Running `app:create-super-admin` fails when a super admin exists; succeeds when `--force` is passed.
2. `testSuperAdminCanInviteGarageOwner`: Super Admin creates garage via `POST /api/admin/garages` and receives activation URL.
3. `testInviteDuplicateOwnerEmailRejected`: Creating garage with an existing email returns 409/422.
4. `testVerifyInvitationTokenValid`: `GET /api/auth/invitation/{token}` returns 200 with `Cache-Control: no-store`.
5. `testVerifyInvitationTokenExpiredOrInvalid`: Returns 404 with identical error body for invalid, used, or expired tokens.
6. `testExpiredTokenRejected`: Token with past `invitationExpiresAt` returns 404.
7. `testAcceptInvitationSetsPasswordAndReturnsJwt`: Valid accept sets password, activates user, clears token hash, and returns JWT.
8. `testTokenIsSingleUse`: Second accept attempt with the same token fails with 404.
9. `testPendingUserCannotLogin`: Login via `POST /api/login_check` fails before acceptance due to `UserChecker`.
10. `testOwnerCanLoginWithNewPassword`: Login succeeds after acceptance with the newly set password.
11. `testResendInvalidatesOldToken`: Calling `/resend-invite` invalidates old token and makes the new token active.
12. `testNoRouteGrantsSuperAdmin`: HTTP endpoints reject any attempt to pass or grant `ROLE_SUPER_ADMIN`.
