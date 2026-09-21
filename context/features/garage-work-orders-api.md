# Feature: Work Orders & Repairs API (`garage-work-orders-api`)

## Overview
Implement tenant-scoped REST API endpoints for managing workshop repair jobs and service visits ([`WorkOrder`](file:///home/clickdrive/Desktop/api/backendGarage/src/Entity/WorkOrder.php)).
This feature powers the primary daily operational workflow for garages: vehicle check-in, tracking repair lifecycle status (`checked_in`, `in_progress`, `completed`, `delivered`, `cancelled`), logging technician diagnoses and parts used, recording labor and parts pricing, and automatically updating vehicle service histories upon job completion.

---

## Security & Multi-Tenant Isolation Guarantees

1. **Role Access**:
   - Both `ROLE_GARAGE_ADMIN` and `ROLE_MECHANIC` have full operational access to create, inspect, update, and manage repair jobs.
   - Protected with `#[IsGranted('ROLE_MECHANIC')]` (inherited by `ROLE_GARAGE_ADMIN`).
   - Unauthenticated requests receive `401 Unauthorized`.
   - Platform Super Admins without a bound workshop (`$user->getGarage() === null`) receive `400 Bad Request`.
2. **Strict Multi-Tenant Scoping**:
   - The garage context is resolved strictly from `$user->getGarage()`.
   - All entity lookups (`WorkOrder`, `Vehicle`, `Customer`) are strictly scoped by the authenticated garage (`findOneBy(['id' => $id, 'garage' => $garage])`).
   - Any attempt to reference or inspect a vehicle or customer belonging to another workshop returns `404 Not Found`.

---

## Work Order Lifecycle & Status Progression

| Status | Meaning | Automatic Timestamp / Side Effects |
| :--- | :--- | :--- |
| `checked_in` | Vehicle received in workshop; mileage recorded; initial issue diagnosed | Auto-populates `checkedInAt = now()` if null. Updates vehicle mileage if higher than current odometer. |
| `in_progress` | Repair or maintenance work actively ongoing | Technician logs diagnosis notes and replacement parts. |
| `completed` | Mechanical work finished; final invoice / price established | Auto-populates `completedAt = now()` if null. Updates vehicle's `lastServiceDate` and `lastServiceMileage`. |
| `delivered` | Vehicle returned / picked up by customer | Auto-populates `pickedUpAt = now()` if null. |
| `cancelled` | Repair cancelled or rejected by customer | Job terminated without updating vehicle service history. |

---

## Endpoints Specification (`/api/garage/work-orders`)

**Base Route**: `#[Route('/api/garage/work-orders', name: 'api_garage_work_orders_')]`
**Security**: `#[IsGranted('ROLE_MECHANIC')]`
**Controller**: `App\Controller\Api\Garage\WorkOrderController`

---

### 1. `GET /api/garage/work-orders`
- **Route Name**: `list`
- **Method**: `GET`
- **Query Parameters**:
  - `status` (`?string`): Filter by lifecycle status (`checked_in`, `in_progress`, `completed`, `delivered`, `cancelled`).
  - `customer_id` (`?string`): Filter work orders for a specific customer.
  - `vehicle_id` (`?string`): Filter work orders for a specific vehicle.
  - `date` (`?string`, format: `Y-m-d`): Filter by exact work order date.
  - `from_date` / `to_date` (`?string`, format: `Y-m-d`): Date range filtering.
  - `query` (`?string`): Case-insensitive search across job description, notes, vehicle license plate, or customer name/phone.
- **Response**: `200 OK`
  ```json
  [
    {
      "id": "019213ef-...",
      "date": "2026-09-21",
      "status": "in_progress",
      "scheduledTime": "09:30",
      "description": "Annual full service and brake pad replacement",
      "price": "240.00",
      "odometerKm": 125000,
      "notes": "Front right brake pad worn down to 10%",
      "partsNotes": "Oil 5W-30, Oil Filter, Front Brake Pads Bosch",
      "checkedInAt": "2026-09-21T07:30:00+00:00",
      "completedAt": null,
      "pickedUpAt": null,
      "createdAt": "2026-09-21T07:30:00+00:00",
      "customer": {
        "id": "019213ab-...",
        "name": "Nikos Papadopoulos",
        "phone": "+306912345678"
      },
      "vehicle": {
        "id": "019213bc-...",
        "licensePlate": "IBZ-1234",
        "make": "Toyota",
        "model": "Yaris"
      }
    }
  ]
  ```

---

### 2. `POST /api/garage/work-orders`
- **Route Name**: `create`
- **Method**: `POST`
- **Request Payload DTO**: `App\DTO\Garage\CreateWorkOrderRequest`
  - `vehicleId` (`string`, required UUID)
  - `customerId` (`?string`, optional UUID; if omitted, automatically inherited from `$vehicle->getCustomer()`)
  - `description` (`string`, required, min 3)
  - `date` (`?string`, format: `Y-m-d`, defaults to current date)
  - `status` (`?string`, choice: `['checked_in', 'in_progress', 'completed', 'delivered', 'cancelled']`, defaults to `checked_in`)
  - `scheduledTime` (`?string`, max 10)
  - `price` (`?string`, regex: `/^\d+(\.\d{1,2})?$/`, defaults to `'0.00'`)
  - `odometerKm` (`?int`, min: 0)
  - `notes` (`?string`)
  - `partsNotes` (`?string`)
- **Behavior**:
  - Resolves `Vehicle` scoped to `$user->getGarage()`. Returns `404 Not Found` if not found.
  - Resolves `Customer`: if provided, must belong to current garage. If omitted, adopts `$vehicle->getCustomer()`.
  - Instantiates `WorkOrder`, sets timestamps, associates with garage, customer, and vehicle.
  - Updates vehicle mileage if `odometerKm` is provided and exceeds current vehicle mileage.
  - Persists and flushes.
- **Response**: `201 Created`

---

### 3. `GET /api/garage/work-orders/{id}`
- **Route Name**: `get`
- **Method**: `GET`
- **Behavior**:
  - Scoped lookup `findOneBy(['id' => $id, 'garage' => $garage])`. Returns `404 Not Found` if missing.
  - Returns complete job details, timestamps, notes, parts notes, customer info, and vehicle specs.
- **Response**: `200 OK`

---

### 4. `PATCH /api/garage/work-orders/{id}`
- **Route Name**: `update`
- **Method**: `PATCH`
- **Request Payload DTO**: `App\DTO\Garage\UpdateWorkOrderRequest`
  - `status` (`?string`, choice: `['checked_in', 'in_progress', 'completed', 'delivered', 'cancelled']`)
  - `date` (`?string`, format: `Y-m-d`)
  - `scheduledTime` (`?string`, max 10)
  - `description` (`?string`, min 3)
  - `price` (`?string`, regex: `/^\d+(\.\d{1,2})?$/`)
  - `odometerKm` (`?int`, min: 0)
  - `notes` (`?string`)
  - `partsNotes` (`?string`)
  - `checkedInAt` (`?string`, ISO-8601 format)
  - `completedAt` (`?string`, ISO-8601 format)
  - `pickedUpAt` (`?string`, ISO-8601 format)
- **Behavior**:
  - Updates mutable fields if non-null.
  - If transitioning to `completed`: auto-sets `completedAt = now()` if null, and updates vehicle's `lastServiceDate` and `lastServiceMileage`.
  - If transitioning to `delivered`: auto-sets `pickedUpAt = now()` if null.
  - Automatically updates `updatedAt`.
- **Response**: `200 OK`

---

### 5. `DELETE /api/garage/work-orders/{id}`
- **Route Name**: `delete`
- **Method**: `DELETE`
- **Behavior**:
  - Scoped lookup `findOneBy(['id' => $id, 'garage' => $garage])`. Returns `404 Not Found` if missing.
  - Removes work order entity from database.
- **Response**: `200 OK` (`{"message": "Work order successfully deleted"}`)

---

## Testing Plan (`tests/Garage/WorkOrderManagementTest.php`)

1. `testMechanicCanCreateWorkOrder`: Checks in a vehicle, asserts default status `checked_in`, auto-populates `checkedInAt`, and inherits customer.
2. `testMechanicCanListWorkOrders`: Lists repair jobs and asserts cross-tenant work orders are absent.
3. `testFilterWorkOrdersByStatus`: Queries by `?status=in_progress` and verifies matching results.
4. `testFilterWorkOrdersByVehicleAndCustomer`: Verifies scoping by vehicle ID and customer ID.
5. `testSearchWorkOrders`: Searches across description, notes, and vehicle plate.
6. `testAdvanceWorkOrderStatusToCompleted`: Transitions to `completed`, verifies `completedAt` is recorded, and verifies vehicle's `lastServiceDate` and `lastServiceMileage` are updated.
7. `testAdvanceWorkOrderStatusToDelivered`: Transitions to `delivered`, verifies `pickedUpAt` timestamp is recorded.
8. `testCrossTenantWorkOrderIsolation`: Garage A cannot view, update, or delete Garage B's work orders (`404 Not Found`).
9. `testCreateWorkOrderRejectsCrossTenantVehicle`: Cannot create work order referencing a vehicle from another garage (`404 Not Found`).
10. `testDeleteWorkOrder`: Deletes a work order successfully.
11. `testSuperAdminWithoutGarageReturnsBadRequest`: Super Admin with `garage = null` receives `400 Bad Request`.
12. `testUnauthenticatedForbidden`: Unauthenticated requests receive `401 Unauthorized`.
