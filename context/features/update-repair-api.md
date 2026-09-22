# Feature Specification: Update Repair API (`update-repair-api`)

## 1. Overview & Objective

Provide a standardized, authenticated REST API endpoint for updating existing work orders / repairs (`WorkOrder`) in the database. When a technician edits repair details (description, price, notes, odometer) or advances the repair lifecycle status (check-in, in progress, completed, delivered), changes are persisted transactionally to the PostgreSQL database with proper audit timestamps and vehicle service history updates.

---

## 2. API Contract & Endpoints

### 2.1 Update Work Order
- **Endpoint**: `PATCH /api/garage/work-orders/{id}`
- **Security**: Bearer JWT token (`Authorization: Bearer <token>`), requires `ROLE_MECHANIC` or `ROLE_GARAGE_ADMIN`.
- **Tenant Scope**: Strictly isolated to `$user->getGarage()`. Attempting to update a work order belonging to another garage returns HTTP 404 Not Found.
- **Headers**:
  - `Content-Type: application/json`
  - `Accept: application/json`
  - `Authorization: Bearer <token>`

#### Request Payload (`UpdateWorkOrderRequest`):
```json
{
  "status": "in_progress",
  "description": "Front brake discs replacement and brake fluid renewal",
  "price": "280.00",
  "odometerKm": 43500,
  "notes": "Installed high-performance ceramic pads as requested.",
  "partsNotes": null,
  "date": "2026-09-22",
  "scheduledTime": "10:30",
  "checkedInAt": "2026-09-22T08:30:00+00:00",
  "completedAt": null,
  "pickedUpAt": null
}
```

*All fields are optional (partial update / patch).*

#### Validation Constraints:
- `status`: One of `['checked_in', 'in_progress', 'completed', 'delivered', 'cancelled']`.
- `description`: String, minimum 3 characters.
- `price`: Decimal format string (e.g. `120.00` or `0.00`), regex `/^\d+(\.\d{1,2})?$/`.
- `odometerKm`: Positive integer or 0.
- `date`: `Y-m-d`.
- `checkedInAt`, `completedAt`, `pickedUpAt`: ISO-8601 formatted datetime string.

#### Side Effects on Status Transitions:
1. When `status` transitions to `checked_in` and `checkedInAt` is not set, sets `checkedInAt = NOW()`.
2. When `status` transitions to `completed`:
   - Sets `completedAt = NOW()` if not already populated.
   - Automatically updates the associated `Vehicle` entity:
     - `vehicle.lastServiceDate = workOrder.date`
     - `vehicle.lastServiceMileage = workOrder.odometerKm`
     - Updates `vehicle.mileage` if odometer exceeds current recorded vehicle mileage.
3. When `status` transitions to `delivered`:
   - Sets `pickedUpAt = NOW()` if not already populated.
4. Updates `updatedAt = NOW()`.

#### Responses:
- **HTTP 200 OK**: Returns updated work order detail representation including customer and vehicle data.
- **HTTP 400 Bad Request**: User not associated with a workshop garage.
- **HTTP 404 Not Found**: Work order does not exist or belongs to another garage tenant.
- **HTTP 422 Unprocessable Content**: Validation constraint violation on payload fields.

---

## 3. Verification Plan

- Execute backend functional test suite:
  `docker compose exec -T php php bin/phpunit tests/Garage/WorkOrderManagementTest.php`
- Verify PATCH updates on status, price, description, odometer, and tenant isolation guards.
