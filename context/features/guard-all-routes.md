# Feature Specification: Guard All Routes (`guard-all-routes`)

## 1. Overview & Objective

Ensure complete route-level security and access control coverage across the backend Symfony application.
This feature verifies, audits, and enforces that:
1. Every API route under `/api` is strictly guarded and requires valid JWT authentication (`IS_AUTHENTICATED_FULLY`), with explicit exceptions only for public authentication and invitation endpoints (`/api/login_check`, `/api/auth/invitation/{token}`, `/api/auth/invitation/accept`).
2. Role hierarchy and permissions are strictly enforced (`ROLE_SUPER_ADMIN` for `/api/admin/*`, `ROLE_MECHANIC` / `ROLE_GARAGE_ADMIN` for `/api/garage/*`).
3. Any attempt to request undefined or non-API routes (such as `/queue`, `/dashboard`, `/admin`) from the backend server does not expose sensitive endpoints or bypass authentication firewalls.
4. An automated, comprehensive functional security test suite (`RouteProtectionTest`) is established to prevent future route regressions.

---

## 2. Security Architecture & Access Control Specifications

### 2.1 Firewall Configuration & Access Rules (`config/packages/security.yaml`)

- **Public Endpoints**:
  - `POST /api/login_check`: Public access for authenticating users.
  - `GET /api/auth/invitation/{token}`: Public access for verifying employee invitations.
  - `POST /api/auth/invitation/accept`: Public access for accepting invitations and setting passwords.
- **Admin Endpoints**:
  - `/api/admin/*`: Restricted strictly to `ROLE_SUPER_ADMIN`.
- **Workshop Garage Endpoints**:
  - `/api/garage/*`: Restricted to authenticated users with `ROLE_MECHANIC` or `ROLE_GARAGE_ADMIN`.
  - `/api/garage/users/*`: Restricted to `ROLE_GARAGE_ADMIN` (or `ROLE_SUPER_ADMIN`).
- **User Profile Endpoints**:
  - `GET /api/auth/me`, `PATCH /api/auth/me`, `POST /api/auth/change-password`: Restricted to `IS_AUTHENTICATED_FULLY`.
- **Catch-all Fallback**:
  - All `/api` routes require `IS_AUTHENTICATED_FULLY`.

### 2.2 Endpoint Protection Matrix

| Route Path | Method | Expected Unauthenticated Status | Required Role |
|---|---|---|---|
| `/api/login_check` | POST | 200 / 401 (Invalid creds) | PUBLIC_ACCESS |
| `/api/auth/invitation/{token}` | GET | 200 / 404 / 410 | PUBLIC_ACCESS |
| `/api/auth/invitation/accept` | POST | 200 / 422 | PUBLIC_ACCESS |
| `/api/auth/me` | GET | 401 Unauthorized | IS_AUTHENTICATED_FULLY |
| `/api/auth/change-password` | POST | 401 Unauthorized | IS_AUTHENTICATED_FULLY |
| `/api/garage/work-orders` | GET | 401 Unauthorized | ROLE_MECHANIC |
| `/api/garage/work-orders` | POST | 401 Unauthorized | ROLE_MECHANIC |
| `/api/garage/vehicles` | GET | 401 Unauthorized | ROLE_MECHANIC |
| `/api/garage/customers` | GET | 401 Unauthorized | ROLE_MECHANIC |
| `/api/garage/lookup` | GET | 401 Unauthorized | ROLE_MECHANIC |
| `/api/garage` | GET | 401 Unauthorized | ROLE_MECHANIC |
| `/api/garage/users` | GET | 401 Unauthorized | ROLE_GARAGE_ADMIN |
| `/api/admin/garages` | GET | 401 Unauthorized | ROLE_SUPER_ADMIN |
| `/queue` | GET | 404 Not Found | N/A (Not an API route) |

---

## 3. Verification & Testing Plan

### 3.1 Automated Security Test Suite (`tests/Security/RouteProtectionTest.php`)
Create a comprehensive `WebTestCase` that validates:
1. `testAllProtectedApiRoutesRejectUnauthenticatedAccess`:
   - Iterates through the list of protected API routes and verifies that each returns HTTP `401 Unauthorized` when requested without an `Authorization` header.
2. `testAdminRoutesRejectMechanicsWithForbidden`:
   - Authenticates as a mechanic (`ROLE_MECHANIC`) and requests `/api/admin/garages`, confirming HTTP `403 Forbidden`.
3. `testGarageAdminRoutesRejectMechanicsWithForbidden`:
   - Authenticates as a mechanic (`ROLE_MECHANIC`) and requests `/api/garage/users`, confirming HTTP `403 Forbidden`.
4. `testPublicEndpointsRemainAccessible`:
   - Confirms `/api/login_check` and `/api/auth/invitation/{token}` do not reject with 401.
5. `testNonExistentFrontendRouteReturns404`:
   - Confirms `GET /queue` returns HTTP 404 without leaking internal application data.

### 3.2 Test Execution
- Run tests via Docker container:
  ```bash
  docker compose exec -T php php bin/phpunit tests/Security/RouteProtectionTest.php
  ```
