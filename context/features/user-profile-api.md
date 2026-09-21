# Feature: User Profile & Self-Service Security API (`user-profile-api`)

## Overview
Implement authenticated self-service account management endpoints allowing any active user on the platform—whether a Platform Super Admin (`ROLE_SUPER_ADMIN`), Workshop Owner (`ROLE_GARAGE_ADMIN`), or Mechanic (`ROLE_MECHANIC`)—to safely update their personal profile details and change their account password.

---

## Security & Architectural Guarantees

1. **Access Level**:
   - Guarded by `#[IsGranted('IS_AUTHENTICATED_FULLY')]`.
   - Unauthenticated requests receive `401 Unauthorized`.
   - Deactivated or soft-deleted accounts are blocked at firewall level via [`UserChecker`](file:///home/clickdrive/Desktop/api/backendGarage/src/Security/UserChecker.php).
2. **Strict Identity Binding**:
   - Endpoints do **not** take user IDs in URL or body. The target entity is always resolved from the authenticated JWT session: `$this->getUser()`.
   - Cross-user tampering is architecturally impossible.
3. **Privilege Escalation Protection**:
   - Profile update endpoints strictly reject/ignore alterations to `roles`, `garage`, `id`, `createdAt`, or `deletedAt`. Users cannot elevate their own permissions or switch workshops.
4. **Current Password Verification**:
   - Password changes strictly require proving knowledge of the existing password via `UserPasswordHasherInterface::isPasswordValid()`. Incorrect current passwords return `400 Bad Request`.
5. **Password Policy Compliance**:
   - New passwords must comply with the platform-wide security standard: minimum 10 characters, maximum 4096, and [`#[Assert\NotCompromisedPassword]`](file:///home/clickdrive/Desktop/api/backendGarage/src/DTO/Auth/ChangePasswordRequest.php).
6. **Email Uniqueness & Conflict Handling**:
   - If an email update is requested, the system performs pre-validation and catches `UniqueConstraintViolationException`, returning `409 Conflict` if the email is already in use by another active account.

---

## Specifications

### Base Route: `/api/auth`
**Security**: `#[IsGranted('IS_AUTHENTICATED_FULLY')]`
**Controller**: `App\Controller\Api\AuthController`

---

### 1. `PATCH /api/auth/me`
- **Route Name**: `api_auth_update_me`
- **Method**: `PATCH`
- **Request Payload DTO**: `App\DTO\Auth\UpdateProfileRequest`
  - `fullName` (`?string`, min: 2, max: 150)
  - `email` (`?string`, valid email format, max: 180)
- **Behavior**:
  - Resolves authenticated user: `$user = $this->getUser()`.
  - If `fullName` is provided, updates `$user->setFullName($dto->fullName)`.
  - If `email` is provided and differs from current email:
    - Checks `UserRepository::findOneBy(['email' => $dto->email])`; returns `409 Conflict` if occupied.
    - Sets `$user->setEmail($dto->email)`.
  - Persists and flushes inside `try / catch (UniqueConstraintViolationException) -> 409 Conflict`.
  - Returns hydrated profile response (matching `GET /api/auth/me`).
- **Response**: `200 OK`
  ```json
  {
    "id": "019213ab-...",
    "email": "new.email@example.com",
    "fullName": "Giannis Sarris",
    "firstName": "Giannis",
    "lastName": "Sarris",
    "roles": ["ROLE_GARAGE_ADMIN"],
    "garage": {
      "id": "019213bc-...",
      "name": "Sarris Garage",
      "subscriptionStatus": "active"
    }
  }
  ```

---

### 2. `POST /api/auth/change-password`
- **Route Name**: `api_auth_change_password`
- **Method**: `POST`
- **Request Payload DTO**: `App\DTO\Auth\ChangePasswordRequest`
  - `currentPassword` (`string`, required)
  - `newPassword` (`string`, required, min: 10, max: 4096, `#[Assert\NotCompromisedPassword]`)
- **Behavior**:
  - Resolves authenticated user: `$user = $this->getUser()`.
  - Verifies `passwordHasher->isPasswordValid($user, $dto->currentPassword)`. If invalid, returns `400 Bad Request` (`{"error": "Invalid current password"}`).
  - Validates that `newPassword !== currentPassword` (returns `422 Unprocessable Content` if identical).
  - Hashes new password and updates `$user->setPassword(...)`.
  - Persists and flushes changes.
- **Response**: `200 OK`
  ```json
  {
    "message": "Password successfully updated"
  }
  ```

---

## Testing Plan (`tests/Auth/UserProfileTest.php`)

1. `testAuthenticatedUserCanUpdateFullName`: Mechanic updates full name; verified in DB and profile response.
2. `testAuthenticatedUserCanUpdateEmail`: Garage Admin updates email address; verified login with new email.
3. `testUpdateEmailFailsOnDuplicateEmail`: Attempting to change email to an already registered active email returns `409 Conflict`.
4. `testUpdateProfileIgnoresRoleEscalationAttempts`: Payload containing `roles` does not elevate user permissions.
5. `testChangePasswordSuccess`: User changes password with valid current password; subsequent login succeeds with new password and fails with old password.
6. `testChangePasswordFailsOnIncorrectCurrentPassword`: Returns `400 Bad Request`.
7. `testChangePasswordEnforcesPasswordPolicy`: Short password (< 10 chars) returns `422 Unprocessable Content`.
8. `testChangePasswordRejectsIdenticalPassword`: Providing the current password as the new password returns `422 Unprocessable Content`.
9. `testSuperAdminCanUpdateProfileAndChangePassword`: Super Admin (`garage = null`) can update their profile and password seamlessly.
10. `testUnauthenticatedRequestsRejected`: Calls without JWT token return `401 Unauthorized`.
