# Feature Specification: Connect Lookup with Backend (`connect-lookup-with-backend`)

## 1. Overview & Objective

Provide a real-time smart lookup API endpoint (`GET /api/garage/lookup`) for the workshop intake workflow. When a technician enters a phone number or a license plate in the check-in modal:
1. **Lookup by Phone**:
   - Locates customer in the database matching the given phone number (accounting for whitespace, dashes, and Greek country code `+30`).
   - Returns the customer details and all associated vehicles owned by this customer.
   - Enables the frontend to auto-fill the customer's name, auto-fill vehicle details if exactly 1 vehicle is owned, or open a selection dropdown with brand and license plate if >1 vehicles are owned.
2. **Lookup by License Plate**:
   - Locates the registered vehicle by license plate (case-insensitive, ignoring hyphens/spaces).
   - Returns the vehicle details (make, model, year) and the associated customer details (name, phone).
   - Enables the frontend to auto-fill vehicle specs and customer name/phone.

---

## 2. API Contract & Endpoints

### 2.1 Smart Lookup Endpoint
- **Endpoint**: `GET /api/garage/lookup`
- **Security**: Bearer JWT token (`Authorization: Bearer <token>`), requires `ROLE_MECHANIC` or `ROLE_GARAGE_ADMIN`.
- **Tenant Scope**: Strictly isolated to `$user->getGarage()`. Data from other garages is never returned.
- **Query Parameters**:
  - `phone` (string, optional): Customer phone number to search.
  - `plate` (string, optional): Vehicle license plate to search.

#### Behavior & Response Schema:

##### Case A: Search by Phone (`GET /api/garage/lookup?phone=6911111101` or `+30 691 111 1101`)
- Matches customer where normalized phone equals normalized database phone.
- Response payload:
```json
{
  "customer": {
    "id": "01a0c875-2456-7870-a49d-a96470cd30e3",
    "name": "Nikos Papadopoulos",
    "firstName": "Nikos",
    "lastName": "Papadopoulos",
    "phone": "+306911111101",
    "email": "nikos@example.com"
  },
  "vehicles": [
    {
      "id": "01a0c875-245d-7f76-ba72-590adc4672bf",
      "licensePlate": "IHB-1234",
      "make": "Peugeot",
      "model": "208",
      "year": 2020
    }
  ]
}
```
If not found:
```json
{
  "customer": null,
  "vehicles": []
}
```

##### Case B: Search by License Plate (`GET /api/garage/lookup?plate=IHB1234` or `IHB-1234`)
- Matches vehicle where normalized plate equals normalized database `license_plate`.
- Response payload:
```json
{
  "vehicle": {
    "id": "01a0c875-245d-7f76-ba72-590adc4672bf",
    "licensePlate": "IHB-1234",
    "make": "Peugeot",
    "model": "208",
    "year": 2020
  },
  "customer": {
    "id": "01a0c875-2456-7870-a49d-a96470cd30e3",
    "name": "Nikos Papadopoulos",
    "firstName": "Nikos",
    "lastName": "Papadopoulos",
    "phone": "+306911111101",
    "email": "nikos@example.com"
  }
}
```
If not found:
```json
{
  "vehicle": null,
  "customer": null
}
```

#### Status Codes:
- **HTTP 200 OK**: Search executed successfully.
- **HTTP 400 Bad Request**: No search query parameter provided or user has no garage.
- **HTTP 401 Unauthorized**: Missing or expired JWT token.

---

## 3. Verification Plan

- Functional test in `tests/Garage/LookupApiTest.php` exercising:
  - Phone search returns customer and single vehicle.
  - Phone search returns customer and multiple vehicles.
  - Phone search returns empty when not found.
  - Plate search returns vehicle and associated customer.
  - Plate search returns empty when not found.
  - Strict tenant garage isolation (garage B cannot see garage A's customers or vehicles).
