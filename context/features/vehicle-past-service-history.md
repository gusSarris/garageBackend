# Feature Specification: Vehicle Past Service History (`vehicle-past-service-history`)

## 1. Overview & Objective

Mechanics and garage owners constantly need to know a motorcycle's past track record when it arrives at the shop or during active repairs:
- *"What work did we do last time?"*
- *"When was the oil or drive belt last replaced and at what mileage?"*
- *"How much did the customer pay previously?"*

This feature introduces a dedicated, high-performance REST API endpoint to retrieve the past service history and work orders for a specific vehicle:
- `GET /api/garage/vehicles/{id}/history`

---

## 2. API Contract & Endpoints

### 2.1 Vehicle Service History Endpoint
- **HTTP Method & Route**: `GET /api/garage/vehicles/{id}/history`
- **Security**: Requires Bearer JWT authentication (`ROLE_MECHANIC` or `ROLE_GARAGE_ADMIN`).
- **Tenant Scope**: Strictly isolated to the authenticated user's garage (`$user->getGarage()`). A 404 Not Found must be returned if the vehicle does not belong to the caller's tenant.

### 2.2 Query Parameters (Optional)
- `limit` (integer, optional, default: 20): Maximum number of past service records to return.
- `exclude_active` (boolean, optional, default: false): When true, excludes work orders currently in progress/active/checked_in so only completed and delivered past jobs are returned.

### 2.3 Response Payload
Returns a JSON array of past work order records sorted chronologically in descending order (`date DESC`, `createdAt DESC`):

```json
[
  {
    "id": "01923e5a-1b45-7389-8921-123456789abc",
    "date": "2025-05-14",
    "status": "delivered",
    "statusLabel": "Παραδόθηκε",
    "description": "Service 20.000km: Αλλαγή λαδιών 10W40, φίλτρο λαδιού, μπουζί, έλεγχος ιμάντα",
    "price": "120.00",
    "odometerKm": 21450,
    "notes": "Έλεγχος τακάκια σε 5.000km",
    "partsNotes": "Μπουζί NGK CR8E",
    "checkedInAt": "2025-05-14T08:30:00+03:00",
    "completedAt": "2025-05-14T17:00:00+03:00",
    "pickedUpAt": "2025-05-15T11:15:00+03:00",
    "createdAt": "2025-05-14T08:30:00+03:00"
  },
  {
    "id": "018f3a12-8c11-7102-a231-abcdef123456",
    "date": "2024-10-02",
    "status": "delivered",
    "statusLabel": "Παραδόθηκε",
    "description": "Αλλαγή ελαστικών Pirelli Diablo Rosso Scooter",
    "price": "180.00",
    "odometerKm": 15800,
    "notes": null,
    "partsNotes": null,
    "checkedInAt": "2024-10-02T09:00:00+03:00",
    "completedAt": "2024-10-02T13:00:00+03:00",
    "pickedUpAt": "2024-10-02T18:00:00+03:00",
    "createdAt": "2024-10-02T09:00:00+03:00"
  }
]
```

If the vehicle has no previous service records, returns an empty array:
```json
[]
```

### 2.4 Status Codes & Error Responses
- **HTTP 200 OK**: Vehicle found and history returned.
- **HTTP 401 Unauthorized**: Missing, invalid, or expired JWT Bearer token.
- **HTTP 404 Not Found**: Vehicle does not exist, or belongs to another tenant garage.
- **HTTP 400 Bad Request**: Invalid UUID format or user not associated with a garage.

---

## 3. Database & Implementation Architecture

- **Controller**: Add action `history(string $id, Request $request)` inside `App\Controller\Api\Garage\VehicleController` (or dedicated controller).
- **Repository**: Query `WorkOrderRepository` by `vehicle = :vehicle` and `garage = :garage`, ordered by `w.date DESC, w.createdAt DESC`.
- **Formatting**: Dedicated helper `formatWorkOrderHistory(WorkOrder $workOrder)` ensuring null-safe dates, numeric price formatting, and human-readable status labels.

---

## 4. Verification Plan

### Automated Tests (`tests/Garage/VehicleHistoryApiTest.php`):
1. **Successful History Retrieval**:
   - Create vehicle with 2 delivered work orders and 1 active work order.
   - Assert `GET /api/garage/vehicles/{id}/history` returns HTTP 200 with chronological order (newest first).
2. **Empty History for New Vehicle**:
   - Create vehicle with zero work orders.
   - Assert endpoint returns HTTP 200 with empty array `[]`.
3. **Tenant Isolation**:
   - Attempt to access history of Vehicle owned by Garage B while authenticated as Garage A user.
   - Assert HTTP 404 Not Found (no cross-tenant leakage).
4. **Security & Role Enforcement**:
   - Unauthenticated request returns HTTP 401 Unauthorized.
