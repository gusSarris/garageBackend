# Feature Specification: Display Database Data on Login (`display-db-data-on-login`)

## 1. Overview & Objective

When a workshop user logs in (e.g. `sarr-c@hotmail.com` / `12345`), the frontend workshop dashboard (`/garage` or `/`) must display live records from the database instead of static mock data.

This specification documents the backend API endpoints and data contract consumed by the frontend to render:
1. **Work Orders & Repairs**: Active repairs in the workshop and queued scheduled appointments.
2. **Customer & Vehicle Information**: Associated customer names, phone numbers, vehicle license plates, and make/models.
3. **Workshop Profile & User Identity**: Tenant garage name ("Sarris Auto Service") and technician identity.

---

## 2. API Contract & Endpoints

### 2.1 Get Workshop Work Orders (Repairs)
- **Endpoint**: `GET /api/garage/work-orders`
- **Security**: Bearer JWT token (`Authorization: Bearer <token>`), requires `ROLE_MECHANIC` or `ROLE_GARAGE_ADMIN`.
- **Query Parameters (Optional)**:
  - `status`: filter by status (`checked_in`, `in_progress`, `completed`, `delivered`, `cancelled`)
  - `query`: substring search across description, customer, vehicle
- **Response Payload (HTTP 200 OK)**:
```json
[
  {
    "id": "01a0c875-2468-74ab-aae8-6c71f14784b4",
    "date": "2026-09-22",
    "status": "in_progress",
    "scheduledTime": "11:00",
    "description": "Air conditioning diagnostic and refrigerant R1234yf recharge",
    "price": "95.00",
    "odometerKm": 28000,
    "notes": "Cabin cooling efficiency test and pollen filter check.",
    "partsNotes": null,
    "checkedInAt": "2026-09-22T08:51:25+00:00",
    "completedAt": null,
    "pickedUpAt": null,
    "createdAt": "2026-09-22T09:31:55+00:00",
    "updatedAt": null,
    "customer": {
      "id": "01a0c875-2458-721c-8149-e13e8dbaa3f1",
      "name": "Eleni Dimitriou",
      "phone": "+306911111104"
    },
    "vehicle": {
      "id": "01a0c875-2461-70d0-8325-f40a0e9de6e0",
      "licensePlate": "YXI-3456",
      "make": "Mazda",
      "model": "CX-30"
    }
  }
]
```

### 2.2 Get User & Workshop Profile
- **Endpoint**: `GET /api/auth/me`
- **Security**: Bearer JWT token (`Authorization: Bearer <token>`).
- **Response Payload (HTTP 200 OK)**:
```json
{
  "id": "01a0c875-22c8-74b3-adf9-b5d8f507c0f6",
  "email": "sarr-c@hotmail.com",
  "fullName": "Kostas Sarris",
  "roles": ["ROLE_GARAGE_ADMIN", "ROLE_USER"],
  "isActive": true,
  "garage": {
    "id": "01a0c875-22c7-7ce3-b75d-35e3131b236c",
    "name": "Sarris Auto Service",
    "subscriptionStatus": "active",
    "isActive": true
  }
}
```

---

## 3. Data Mapping & Status Alignment

| Database / API Status | Dashboard UI Tab | UI Status Filter | UI Display Badge |
|:---|:---|:---|:---|
| `in_progress` | Active (`/garage`, `/`) | `in_progress` | Σε εξέλιξη |
| `checked_in` | Active (`/garage`, `/`) | `all` | Αναμ. για επισκευή |
| `completed` (without `pickedUpAt`) | Active (`/garage`, `/`) | `completed` | Έτοιμο για παράδοση |
| `delivered` / has `pickedUpAt` | Archived | - | Παραδόθηκε |
| `cancelled` | Queue / Archived | - | Ακυρώθηκε |
| `scheduled` (or scheduledTime without checkedInAt) | Queue (`/queue`) | - | Ώρα ραντεβού (π.χ. 09:00) |

---

## 4. Verification Plan

- Run functional test suites in `backendGarage`:
  `docker compose exec -T php php bin/phpunit tests/Garage/WorkOrderManagementTest.php`
  `docker compose exec -T php php bin/phpunit tests/Cors/CorsHeadersTest.php`
- Verify that Bearer token authorization operates cleanly and returns the expected seeded data from the database.
