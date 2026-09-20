# Feature: Create Vehicles Table

## Overview
Implement the `Vehicle` Doctrine ORM entity and database table using **UUIDv7** primary keys.
Vehicles represent client-owned automobiles, commercial vans, motorcycles, or fleet vehicles serviced by a specific auto repair garage.
Every vehicle is linked to a **Tenant Garage** (`Garage`) for strict multi-tenant isolation, and to an owner (**Customer**).

## Prerequisites
- `symfony/uid` is already installed and configured.
- `Garage` and `Customer` entities exist with UUIDv7 identifiers.

## Specifications

### Entity: `Vehicle`
- **Table**: `vehicle`
- **Primary Key**: `id` (`uuid`, UUIDv7 generated via `doctrine.uuid_generator`)
- **Fields**:
  - `licensePlate`: `string(20)`, not null (Vehicle registration plate / πινακίδα κυκλοφορίας, primary identifier for workshops)
  - `vin`: `string(17)`, nullable (Vehicle Identification Number / Chassis number / Αριθμός Πλαισίου)
  - `make`: `string(100)`, not null (Brand/Manufacturer, e.g. Toyota, Volkswagen, Ford)
  - `model`: `string(100)`, not null (Model line, e.g. Yaris, Golf, Focus)
  - `year`: `integer`, nullable (Model/manufacture year, e.g. 2018)
  - `engineCode`: `string(50)`, nullable (Engine designation code, e.g. CXXB, 1KR-FE; essential for ordering spare parts)
  - `engineDisplacement`: `integer`, nullable (Engine displacement in cubic centimeters / cc, e.g. 1598)
  - `enginePowerHp`: `integer`, nullable (Engine power output in horsepower / hp, e.g. 115)
  - `fuelType`: `string(50)`, nullable (e.g. 'gasoline', 'diesel', 'hybrid', 'electric', 'lpg', 'cng')
  - `transmission`: `string(50)`, nullable (e.g. 'manual', 'automatic', 'semi-automatic')
  - `color`: `string(50)`, nullable (e.g. 'White', 'Black Metallic', 'Silver')
  - `firstRegistrationDate`: `date_immutable`, nullable (First registration date / άδεια κυκλοφορίας)
  - `mileage`: `integer`, nullable (Current or last observed odometer reading in kilometers)
  - `lastServiceDate`: `date_immutable`, nullable (Ημερομηνία τελευταίου καταγεγραμμένου service)
  - `lastServiceMileage`: `integer`, nullable (Χιλιόμετρα στο τελευταίο service)
  - `nextKteoDate`: `date_immutable`, nullable (Ημερομηνία λήξης ΚΤΕΟ / επόμενης επιθεώρησης για αυτοματοποιημένη υπενθύμιση)
  - `nextServiceDate`: `date_immutable`, nullable (Ημερομηνία επόμενου service για υπενθύμιση)
  - `nextServiceMileage`: `integer`, nullable (Χιλιόμετρα επόμενου προγραμματισμένου service)
  - `lastKteoReminderSentAt`: `datetime_immutable`, nullable (Timestamp τελευταίας αποστολής υπενθύμισης ΚΤΕΟ για αποφυγή διπλών ειδοποιήσεων)
  - `lastServiceReminderSentAt`: `datetime_immutable`, nullable (Timestamp τελευταίας αποστολής υπενθύμισης service για αποφυγή διπλών ειδοποιήσεων)
  - `allowReminders`: `boolean`, not null, default: `true` (Συγκατάθεση πελάτη για λήψη αυτοματοποιημένων υπενθυμίσεων SMS/Email)
  - `notes`: `text`, nullable (Mechanic notes, quirks, special servicing observations)
  - `createdAt`: `datetime_immutable`, not null (Initialized to current timestamp)
  - `updatedAt`: `datetime_immutable`, nullable
- **Relations**:
  - `garage`: `ManyToOne` relation to `Garage` (inversedBy: `vehicles`, nullable: false, onDelete: "CASCADE")
  - `customer`: `ManyToOne` relation to `Customer` (inversedBy: `vehicles`, nullable: false, onDelete: "CASCADE")
- **Relation Updates on Existing Entities**:
  - `Garage`: add `vehicles` (`OneToMany` relation to `Vehicle`, mappedBy: `garage`, cascade: ["remove"], orphanRemoval: true)
  - `Customer`: add `vehicles` (`OneToMany` relation to `Vehicle`, mappedBy: `customer`, cascade: ["remove"], orphanRemoval: true)
- **Indexes**:
  - `INDEX (garage_id, license_plate)`: Fast workshop lookups by plate within a tenant garage
  - `INDEX (garage_id, vin)`: Fast lookup by chassis number / VIN
  - `INDEX (garage_id, next_kteo_date)`: Fast query for upcoming KTEO expiration reminders (e.g. within next 30 days)
  - `INDEX (garage_id, next_service_date)`: Fast query for upcoming service reminders
  - `INDEX (customer_id)`: Quick listing of vehicles belonging to a specific customer
  - `INDEX (garage_id, created_at)`: Fast ordering and listing of recently added vehicles

### Database & Migrations
- Generate migration via `docker compose exec -T php bin/console make:migration`.
- Execute migration via `docker compose exec -T php bin/console doctrine:migrations:migrate --no-interaction`.

### Testing
- Service/Kernel integration test (`tests/VehiclePersistenceTest.php`) verifying:
  - Vehicle entity creation and persistence with UUIDv7.
  - Associations with `Garage` and `Customer`.
  - Persisting mechanical attributes (engine code, displacement, fuel type, mileage).
  - Persisting service tracking & reminder fields (`lastServiceDate`, `lastServiceMileage`, `nextKteoDate`, `nextServiceDate`, `nextServiceMileage`, reminder sent timestamps, `allowReminders`).
  - Nullable field handling.
  - Cascade removal: deleting a customer or garage cascades deletion of the associated vehicles.
