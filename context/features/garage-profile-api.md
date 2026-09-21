# Feature: Garage Profile API (`garage-profile-api`)

## Overview
Implement tenant-scoped workshop profile management for workshop administrators (`ROLE_GARAGE_ADMIN`).
This feature allows the authenticated workshop owner to inspect their garage profile and safely update operational and customer-facing contact details while strictly protecting legal, billing, and platform-managed fields from unauthorized alteration.

## Field Protection Policy

| Field | Tenant Access | Justification |
| :--- | :---: | :--- |
| `id` | Read-Only | Technical primary key (UUIDv7). Immutable. |
| `created_at` | Read-Only | Audit timestamp of workshop registration. Immutable. |
| `subscription_status` | **Read-Only** | Controlled exclusively by platform billing / Stripe / Super Admin. |
| `is_active` | **Read-Only** | Operational account status; tenants cannot self-activate/deactivate. |
| `vat_number` (ΑΦΜ) | **Read-Only** | Legal entity and tax identifier. Modifications require Super Admin / Support verification. |
| `tax_office` (ΔΟΥ) | **Read-Only** | Tax authority bound to VAT registration. |
| `name` | **Editable** | Workshop commercial name / trade name. |
| `email` | **Editable** | Workshop communication email address. |
| `phone` | **Editable** | Primary workshop landline or mobile for customer notifications. |
| `address` | **Editable** | Physical street address. |
| `city` | **Editable** | City / municipality. |
| `postal_code` | **Editable** | Postal / ZIP code. |

## Multi-Tenant Isolation & Security Guarantees

1. **Strict Context Binding (Zero Cross-Tenant Leakage)**:
   - The endpoint `/api/garage` does **NOT** accept an `{id}` in the URL or in request parameters.
   - The target `Garage` entity is resolved strictly from the authenticated user token: `$user->getGarage()`.
   - It is impossible for a garage owner to inspect or modify another workshop's entry.
2. **No Garage Creation by Tenants**:
   - Only `ROLE_SUPER_ADMIN` can onboard new garages (`POST /api/admin/garages`).
   - The tenant API `/api/garage` does NOT expose any `POST` method. Calling `POST /api/garage` returns HTTP 405 Method Not Allowed.
   - If a `ROLE_GARAGE_ADMIN` attempts to call `POST /api/admin/garages`, Symfony access control immediately rejects it with HTTP 403 Forbidden.
3. **No Garage Deletion by Tenants**:
   - Deleting or decommissioning a tenant account is strictly a Platform Super Admin responsibility. No `DELETE /api/garage` endpoint exists.

## Endpoints Specification

### 1. `GET /api/garage`
- **Route**: `#[Route('/api/garage', name: 'api_garage_profile', methods: ['GET'])]`
- **Security**: `#[IsGranted('ROLE_GARAGE_ADMIN')]`
- **Behavior**: Retrieves the profile of the garage bound to the authenticated user (`$user->getGarage()`).
- **Response**: `200 OK`
  ```json
  {
    "id": "01a0bf66-53d5-7063-9bdd-30f27c424b43",
    "name": "Auto Moto Service Sarris",
    "email": "contact@automoto.gr",
    "phone": "+30 210 1234567",
    "vatNumber": "EL998877665",
    "taxOffice": "Δ' Αθηνών",
    "address": "Leoforos Athinon 100",
    "city": "Athens",
    "postalCode": "10447",
    "subscriptionStatus": "active",
    "isActive": true,
    "createdAt": "2026-09-20T12:00:00+00:00",
    "updatedAt": "2026-09-20T16:00:00+00:00"
  }
  ```

### 2. `PATCH /api/garage`
- **Route**: `#[Route('/api/garage', name: 'api_garage_update_profile', methods: ['PATCH'])]`
- **Security**: `#[IsGranted('ROLE_GARAGE_ADMIN')]`
- **Request DTO**: `App\DTO\Garage\UpdateGarageProfileRequest`
  - `name`: `?string`, max 255
  - `email`: `?string`, email format, max 255
  - `phone`: `?string`, max 50
  - `address`: `?string`, max 255
  - `city`: `?string`, max 100
  - `postalCode`: `?string`, max 20
- **Behavior**:
  - Validates request payload via `#[MapRequestPayload]`.
  - Updates only provided non-null fields on the authenticated user's `Garage`.
  - Automatically updates `updatedAt` to `new \DateTimeImmutable()`.
  - Ignores any client attempt to submit `vatNumber`, `taxOffice`, `subscriptionStatus`, or `isActive`.
  - Persists and flushes changes.
- **Response**: `200 OK` with hydrated profile object.

## Implementation Architecture

1. **Controller**: `App\Controller\Api\Garage\GarageProfileController`
2. **DTO**: `App\DTO\Garage\UpdateGarageProfileRequest`
3. **Tests**: `tests/Garage/GarageProfileTest.php` (`WebTestCase`)
   - `testGarageAdminCanViewOwnProfile`
   - `testGarageAdminCanUpdateProfile`
   - `testProtectedFieldsAreNotModifiedOnUpdate`
   - `testMechanicForbiddenFromUpdatingProfile`
   - `testGarageAdminForbiddenFromCreatingGarages`
   - `testSuperAdminWithoutGarageReturnsBadRequest`
   - `testUnauthenticatedForbidden`
   - `testInvalidEmailReturns422`
