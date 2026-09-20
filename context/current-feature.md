# Current Feature

- **Feature Name**: None
- **Branch**: None
- **Status**: Idle
- **Specification**: None

## Goals
None

## Notes
None

## History
- **create-work-orders-table** (Completed: 2026-09-20):
  - Scaffolded `WorkOrder` entity and repository via MakerBundle with UUIDv7 primary key.
  - Connected `WorkOrder` with `ManyToOne` relations to `Garage`, `Customer`, and `Vehicle` with `onDelete: 'CASCADE'`.
  - Configured `OneToMany` collections on `Garage`, `Customer`, and `Vehicle` with `cascade: ['remove']` and `orphanRemoval: true`.
  - Added SMS/notification lifecycle fields (`date`, `status`, `scheduledTime`, `description`, `price`, `odometerKm`, `notes`, `partsNotes`, `checkedInAt`, `completedAt`, `pickedUpAt`).
  - Added composite indexes: `(garage_id, status)`, `(vehicle_id, date)`, `(customer_id, date)`, `(garage_id, date)`.
  - Generated and executed migration `Version20260920102321` across dev and test environments.
  - Implemented KernelTestCase integration tests (`tests/WorkOrderPersistenceTest.php`). Total 13 tests, 130 assertions passing across the test suite.
- **create-vehicles-table** (Completed: 2026-09-20):
  - Scaffolded `Vehicle` entity and repository via MakerBundle with UUIDv7 primary key.
  - Connected `Vehicle` with `ManyToOne` to `Garage` and `Customer` with `onDelete: 'CASCADE'`.
  - Configured `OneToMany` relations in `Garage` and `Customer` with `cascade: ['remove']` and `orphanRemoval: true`.
  - Added mechanical attributes and service/KTEO reminder tracking fields (`lastServiceDate`, `lastServiceMileage`, `nextKteoDate`, `nextServiceDate`, `nextServiceMileage`, `lastKteoReminderSentAt`, `lastServiceReminderSentAt`, `allowReminders`).
  - Added composite indexes: `(garage_id, license_plate)`, `(garage_id, vin)`, `(garage_id, next_kteo_date)`, `(garage_id, next_service_date)`, `(customer_id)`, `(garage_id, created_at)`.
  - Generated and executed migration `Version20260920075243` across dev and test environments.
  - Implemented KernelTestCase integration tests (`tests/VehiclePersistenceTest.php`). Total 7 tests, 67 assertions passing.
- **create-garages-and-customers-tables** (Completed: 2026-09-20):
  - Added `symfony/uid` via Flex for native UUIDv7 support.
  - Scaffolded `Garage` and `Customer` entities and repositories via MakerBundle with UUIDv7 primary keys.
  - Configured `ManyToOne` (Customer -> Garage) and `OneToMany` (Garage -> Customer) with `orphanRemoval: true` and cascade remove.
  - Added composite indexes on Customer table: `(garage_id, phone)`, `(garage_id, last_name)`, `(garage_id, created_at)`.
  - Generated and executed migration `Version20260920060134`.
  - Implemented persistence integration tests (`tests/GarageCustomerPersistenceTest.php`) verifying UUIDv7, B2B fields, and cascade deletion. 3 tests, 26 assertions passing.
