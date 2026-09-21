# Feature: Tenant Status & Subscription Guard (`tenant-status-listener`)

## Overview
Implement automated, platform-wide enforcement of workshop status (`garage.isActive`) and subscription validity (`garage.subscriptionStatus`) via a Symfony Event Listener ([`TenantStatusListener`](file:///home/clickdrive/Desktop/api/backendGarage/src/EventListener/TenantStatusListener.php)).
While [`UserChecker`](file:///home/clickdrive/Desktop/api/backendGarage/src/Security/UserChecker.php) validates individual user active and soft-deleted states, this feature closes the critical SaaS monetization and compliance gap by automatically locking out all workshop personnel (owners and mechanics) whenever a workshop tenant is deactivated (`isActive = false`) or its subscription has lapsed (`cancelled`, `expired`, `past_due`, `suspended`).

---

## Security & Architectural Guarantees

1. **Global Interception Point**:
   - Handled via `App\EventListener\TenantStatusListener` on `KernelEvents::REQUEST` (priority `0`, running immediately after Symfony Firewall authentication).
   - Checks the authenticated user's associated `Garage` entity.
2. **Access Control & Bypasses**:
   - **Public Requests**: Unauthenticated requests (`/api/login_check`, `/api/auth/invitation/*`) proceed without evaluation.
   - **Super Admins**: Users possessing `ROLE_SUPER_ADMIN` bypass the guard entirely, ensuring platform operators can still inspect, administer, and restore garages.
   - **Self-Inspection Exemption**: `GET /api/auth/me` is explicitly exempted from blocking so that client applications can retrieve authenticated profile state, diagnose the lockdown reason (`subscriptionStatus`, `isActive`), and prompt the user to renew or contact support.
3. **Lockdown Scope**:
   - All tenant-scoped workshop endpoints under `^/api/garage` (profile, staff management, customers, vehicles, work orders) are strictly guarded.
4. **Subscription Policy**:
   - **Allowed States**: `trial`, `active`.
   - **Disallowed States**: `expired`, `cancelled`, `past_due`, `suspended`, or any state other than `trial` or `active`.
   - **Deactivated State**: Any workshop with `isActive === false`, regardless of subscription status.
5. **Response on Lockdown**:
   - HTTP `403 Forbidden` with diagnostic payload:
     ```json
     {
       "error": "Workshop is inactive or subscription has expired.",
       "code": "WORKSHOP_INACTIVE_OR_EXPIRED",
       "isActive": false,
       "subscriptionStatus": "cancelled"
     }
     ```

---

## Technical Specifications

### Event Listener: `App\EventListener\TenantStatusListener`
- **Hook**: `#[AsEventListener(event: KernelEvents::REQUEST, priority: 0)]`
- **Dependencies**:
  - `Symfony\Bundle\SecurityBundle\Security` (to resolve current user and roles).
- **Execution Logic**:
  ```php
  // 1. Only process main requests
  if (!$event->isMainRequest()) {
      return;
  }

  // 2. Only process tenant routes (^/api/garage)
  $path = $event->getRequest()->getPathInfo();
  if (!str_starts_with($path, '/api/garage')) {
      return;
  }

  // 3. Resolve authenticated user
  $user = $this->security->getUser();
  if (!$user instanceof User) {
      return;
  }

  // 4. Super Admins bypass
  if ($this->security->isGranted('ROLE_SUPER_ADMIN')) {
      return;
  }

  // 5. Evaluate tenant status
  $garage = $user->getGarage();
  if (!$garage instanceof Garage) {
      $event->setResponse(new JsonResponse(['error' => 'No garage associated with this account.'], Response::HTTP_BAD_REQUEST));
      return;
  }

  $isValidSubscription = in_array($garage->getSubscriptionStatus(), ['trial', 'active'], true);
  $isActive = $garage->isActive();

  if (!$isActive || !$isValidSubscription) {
      $event->setResponse(new JsonResponse([
          'error' => 'Workshop is inactive or subscription has expired.',
          'code' => 'WORKSHOP_INACTIVE_OR_EXPIRED',
          'isActive' => $isActive,
          'subscriptionStatus' => $garage->getSubscriptionStatus(),
      ], Response::HTTP_FORBIDDEN));
  }
  ```

---

## Testing Plan (`tests/Security/TenantStatusListenerTest.php`)

1. `testActiveWorkshopWithTrialSubscriptionCanAccess`: Verifies `200 OK` for `/api/garage` when `isActive = true` and `subscriptionStatus = 'trial'`.
2. `testActiveWorkshopWithActiveSubscriptionCanAccess`: Verifies `200 OK` for `/api/garage` when `isActive = true` and `subscriptionStatus = 'active'`.
3. `testInactiveWorkshopIsBlockedWith403`: Verifies `403 Forbidden` with error code `WORKSHOP_INACTIVE_OR_EXPIRED` when `isActive = false`.
4. `testCancelledSubscriptionIsBlockedWith403`: Verifies `403 Forbidden` when `subscriptionStatus = 'cancelled'`.
5. `testExpiredSubscriptionIsBlockedWith403`: Verifies `403 Forbidden` when `subscriptionStatus = 'expired'`.
6. `testPastDueSubscriptionIsBlockedWith403`: Verifies `403 Forbidden` when `subscriptionStatus = 'past_due'`.
7. `testAllGarageSubroutesAreBlocked`: Verifies lockout across `/api/garage`, `/api/garage/users`, `/api/garage/customers`, `/api/garage/vehicles`, and `/api/garage/work-orders`.
8. `testAuthMeRemainsAccessibleForInactiveWorkshop`: Verifies that `GET /api/auth/me` returns `200 OK` and correctly reveals `subscriptionStatus` and `isActive = false`.
9. `testSuperAdminBypassesTenantGuard`: Verifies Super Admin can execute platform operations (`/api/admin/*`) unaffected.
10. `testMechanicBlockedWhenWorkshopInactive`: Verifies mechanics are equally blocked when the workshop is inactive.
