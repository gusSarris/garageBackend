# Feature: Customers & Vehicles API (`garage-customers-vehicles-api`)

## Overview
Implement tenant-scoped REST API endpoints for managing workshop vehicle owners ([`Customer`](file:///home/clickdrive/Desktop/api/backendGarage/src/Entity/Customer.php)) and their vehicles ([`Vehicle`](file:///home/clickdrive/Desktop/api/backendGarage/src/Entity/Vehicle.php)).
This feature empowers workshop personnel (both mechanics and administrators) to register vehicle owners, search vehicles by license plate or VIN, track mechanical specifications, and monitor upcoming maintenance and KTEO inspection dates.

---

## Security & Multi-Tenant Isolation Guarantees

1. **Role Access**:
   - Both `ROLE_GARAGE_ADMIN` and `ROLE_MECHANIC` have full operational access to customers and vehicles.
   - Guarded via `#[IsGranted('ROLE_MECHANIC')]` (inherited by `ROLE_GARAGE_ADMIN`).
   - Unauthenticated requests receive `401 Unauthorized`.
   - Super Admins without a bound garage (`$user->getGarage() === null`) receive `400 Bad Request`.
2. **Strict Multi-Tenant Scoping**:
   - The garage context is resolved strictly from `$user->getGarage()`.
   - All entity queries (`find`, `search`, `delete`, `update`) are scoped by `garage`: `findOneBy(['id' => $id, 'garage' => $garage])`.
   - Cross-tenant lookups uniformly return `404 Not Found`.
   - When registering a vehicle, the referenced `customerId` must exist within the caller's garage; referencing a customer from another garage returns `404 Not Found`.

---

## Customers API (`/api/garage/customers`)

**Base Route**: `#[Route('/api/garage/customers', name: 'api_garage_customers_')]`
**Security**: `#[IsGranted('ROLE_MECHANIC')]`
**Controller**: `App\Controller\Api\Garage\CustomerController`

### 1. `GET /api/garage/customers`
- **Route Name**: `list`
- **Method**: `GET`
- **Query Parameters**:
  - `query` (`?string`): Case-insensitive substring match on `phone`, `lastName`, `firstName`, `companyName`, or `email`.
- **Response**: `200 OK`
  ```json
  [
    {
      "id": "019213ab-...",
      "firstName": "Nikos",
      "lastName": "Papadopoulos",
      "companyName": null,
      "vatNumber": null,
      "taxOffice": null,
      "phone": "+306912345678",
      "secondaryPhone": null,
      "email": "nikos@example.com",
      "address": "Leoforos Dimokratias 15",
      "city": "Athens",
      "postalCode": "10431",
      "notes": "Prefers afternoon appointments",
      "createdAt": "2026-09-21T10:00:00+00:00",
      "vehiclesCount": 1
    }
  ]
  ```

### 2. `POST /api/garage/customers`
- **Route Name**: `create`
- **Method**: `POST`
- **Request Payload DTO**: `App\DTO\Garage\CreateCustomerRequest`
  - `phone` (`string`, required, max: 50)
  - `firstName` (`?string`, max: 100)
  - `lastName` (`?string`, max: 100)
  - `companyName` (`?string`, max: 255)
  - `vatNumber` (`?string`, max: 50)
  - `taxOffice` (`?string`, max: 100)
  - `secondaryPhone` (`?string`, max: 50)
  - `email` (`?string`, valid email format, max: 255)
  - `address` (`?string`, max: 255)
  - `city` (`?string`, max: 100)
  - `postalCode` (`?string`, max: 20)
  - `notes` (`?string`)
- **Behavior**:
  - Validates payload via `#[MapRequestPayload]`.
  - Creates and persists `Customer` associated with `$user->getGarage()`.
- **Response**: `201 Created`

### 3. `GET /api/garage/customers/{id}`
- **Route Name**: `get`
- **Method**: `GET`
- **Behavior**:
  - Tenant-scoped lookup `findOneBy(['id' => $id, 'garage' => $garage])`. Returns `404 Not Found` if missing.
  - Returns full customer profile and list of registered vehicles.
- **Response**: `200 OK`

### 4. `PATCH /api/garage/customers/{id}`
- **Route Name**: `update`
- **Method**: `PATCH`
- **Request Payload DTO**: `App\DTO\Garage\UpdateCustomerRequest`
  - All fields nullable; only non-null values are updated. Automatically updates `updatedAt`.
- **Response**: `200 OK`

### 5. `DELETE /api/garage/customers/{id}`
- **Route Name**: `delete`
- **Method**: `DELETE`
- **Behavior**:
  - Tenant-scoped lookup. Returns `404 Not Found` if missing.
  - Removes customer (and cascades to vehicles/work orders per doctrine mapping).
- **Response**: `200 OK` (`{"message": "Customer successfully deleted"}`)

---

## Vehicles API (`/api/garage/vehicles`)

**Base Route**: `#[Route('/api/garage/vehicles', name: 'api_garage_vehicles_')]`
**Security**: `#[IsGranted('ROLE_MECHANIC')]`
**Controller**: `App\Controller\Api\Garage\VehicleController`

### 1. `GET /api/garage/vehicles`
- **Route Name**: `list`
- **Method**: `GET`
- **Query Parameters**:
  - `query` (`?string`): Case-insensitive search on `licensePlate`, `vin`, `make`, or `model`.
  - `customer_id` (`?string`): Filter vehicles belonging to a specific customer.
  - `upcoming_kteo` (`?int`): Returns vehicles whose `nextKteoDate` is within next $N$ days (or overdue).
  - `upcoming_service` (`?int`): Returns vehicles whose `nextServiceDate` is within next $N$ days (or overdue).
- **Response**: `200 OK`
  ```json
  [
    {
      "id": "019213bc-...",
      "licensePlate": "IBZ-1234",
      "vin": "WBA1234567890ABCD",
      "make": "Toyota",
      "model": "Yaris",
      "year": 2018,
      "fuelType": "Hybrid",
      "transmission": "Automatic",
      "mileage": 85000,
      "nextKteoDate": "2026-10-15",
      "nextServiceDate": "2026-11-01",
      "allowReminders": true,
      "customer": {
        "id": "019213ab-...",
        "name": "Nikos Papadopoulos",
        "phone": "+306912345678"
      }
    }
  ]
  ```

### 2. `POST /api/garage/vehicles`
- **Route Name**: `create`
- **Method**: `POST`
- **Request Payload DTO**: `App\DTO\Garage\CreateVehicleRequest`
  - `customerId` (`string`, required UUID)
  - `licensePlate` (`string`, required, max: 20)
  - `make` (`string`, required, max: 100)
  - `model` (`string`, required, max: 100)
  - `vin` (`?string`, max: 17)
  - `year` (`?int`, min: 1900, max: 2100)
  - `engineCode` (`?string`, max: 50)
  - `engineDisplacement` (`?int`)
  - `enginePowerHp` (`?int`)
  - `fuelType` (`?string`, max: 50)
  - `transmission` (`?string`, max: 50)
  - `color` (`?string`, max: 50)
  - `firstRegistrationDate` (`?string`, format: `Y-m-d`)
  - `mileage` (`?int`, min: 0)
  - `lastServiceDate` (`?string`, format: `Y-m-d`)
  - `lastServiceMileage` (`?int`, min: 0)
  - `nextKteoDate` (`?string`, format: `Y-m-d`)
  - `nextServiceDate` (`?string`, format: `Y-m-d`)
  - `nextServiceMileage` (`?int`, min: 0)
  - `allowReminders` (`bool`, default: true)
  - `notes` (`?string`)
- **Behavior**:
  - Resolves `Customer` by `customerId` scoped to the current garage (`findOneBy(['id' => $dto->customerId, 'garage' => $garage])`). If not found, returns `404 Not Found` (Customer not found).
  - Normalizes `licensePlate` (trimmed, uppercase).
  - Persists and flushes `Vehicle` entity.
- **Response**: `201 Created`

### 3. `GET /api/garage/vehicles/{id}`
- **Route Name**: `get`
- **Method**: `GET`
- **Behavior**:
  - Tenant-scoped lookup `findOneBy(['id' => $id, 'garage' => $garage])`. Returns `404 Not Found` if missing.
  - Returns complete vehicle data, maintenance tracking milestones, and owner information.
- **Response**: `200 OK`

### 4. `PATCH /api/garage/vehicles/{id}`
- **Route Name**: `update`
- **Method**: `PATCH`
- **Request Payload DTO**: `App\DTO\Garage\UpdateVehicleRequest`
  - Updates mutable attributes (mileage, service dates, KTEO dates, specs, reminders, notes, or reassignment of `customerId` within the same garage).
  - Automatically updates `updatedAt`.
- **Response**: `200 OK`

### 5. `DELETE /api/garage/vehicles/{id}`
- **Route Name**: `delete`
- **Method**: `DELETE`
- **Behavior**:
  - Tenant-scoped lookup `findOneBy(['id' => $id, 'garage' => $garage])`. Returns `404 Not Found` if missing.
  - Removes vehicle.
- **Response**: `200 OK` (`{"message": "Vehicle successfully deleted"}`)

---

## Testing Plan (`tests/Garage/CustomerVehicleManagementTest.php`)

1. `testMechanicCanCreateAndListCustomer`: Mechanic registers a customer and lists them.
2. `testMechanicCanCreateVehicleForCustomer`: Mechanic registers a vehicle with complete specs and KTEO/service dates.
3. `testVehicleRequiresCustomerInSameGarage`: Cannot create a vehicle with a `customerId` from another garage (`404 Not Found`).
4. `testCrossTenantCustomerIsolation`: Garage A cannot view, update, or delete Garage B's customers (`404 Not Found`).
5. `testCrossTenantVehicleIsolation`: Garage A cannot view, update, or delete Garage B's vehicles (`404 Not Found`).
6. `testVehicleSearchByLicensePlateAndVin`: Queries vehicles by plate prefix and VIN.
7. `testVehicleUpcomingKteoFilter`: Filters vehicles with `?upcoming_kteo=30` and verifies date matching.
8. `testVehicleUpcomingServiceFilter`: Filters vehicles with `?upcoming_service=30` and verifies date matching.
9. `testUpdateCustomerAndVehicle`: Updates contact information and odometer/service dates.
10. `testDeleteVehicleAndCustomer`: Deletes vehicle and customer independently.
11. `testSuperAdminWithoutGarageReturnsBadRequest`: Super Admin with `garage = null` receives `400 Bad Request`.
12. `testUnauthenticatedForbidden`: Unauthenticated requests receive `401 Unauthorized`.
