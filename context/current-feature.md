# Current Feature

- **Feature Name**: none
- **Branch**: main
- **Status**: Idle
- **Specification**: none

## Goals

## Notes

## History
- **create-messaging-schema** (Completed: 2026-09-20):
  - Scaffolded `Notification` and `SmsTemplate` entities and repositories via MakerBundle with native UUIDv7 primary keys.
  - Implemented `Notification` audit logging for Native Deep Links (`sms:`, `viber:`) and EasySMS API with delivery status, cost, provider tracking, and error messaging.
  - Implemented `SmsTemplate` with workshop customization, dynamic variables, and active state management.
  - Connected `Notification` to `Garage` (`ManyToOne`, CASCADE), `Customer` (`ManyToOne`, CASCADE), `Vehicle` (`ManyToOne`, SET NULL), and `WorkOrder` (`ManyToOne`, SET NULL).
  - Connected `SmsTemplate` to `Garage` (`ManyToOne`, CASCADE).
  - Configured `OneToMany` collections on `Garage`, `Customer`, `Vehicle`, and `WorkOrder`.
  - Added composite indexes: `(garage_id, created_at)`, `(garage_id, type)`, `(customer_id, created_at)`, `(work_order_id)` on notification, and `(garage_id, type)`, `(garage_id, is_active)` on sms_template.
  - Generated and executed migration `Version20260920132426` across dev and test environments.
  - Implemented KernelTestCase integration tests (`tests/MessagingPersistenceTest.php`). Total 23 tests, 235 assertions passing across the test suite.
- **create-users-table** (Completed: 2026-09-20):
  - Installed `symfony/security-bundle` via Flex and configured security password hasher and user provider.
  - Scaffolded `User` entity and repository via MakerBundle with UUIDv7 primary key and database table `app_user`.
  - Implemented `UserInterface` and `PasswordAuthenticatedUserInterface` supporting `ROLE_GARAGE_ADMIN` (owner) and `ROLE_MECHANIC` with security role hierarchy.
  - Connected `User` with `ManyToOne` relation to `Garage` (tenant) with `onDelete: 'CASCADE'`.
  - Configured `OneToMany` collection on `Garage` with `cascade: ['remove']` and `orphanRemoval: true`.
  - Added composite indexes: `UNIQUE (email)`, `(garage_id)`, and `(garage_id, is_active)`.
  - Generated and executed migration `Version20260920125157` across dev and test environments.
  - Implemented KernelTestCase integration tests (`tests/UserPersistenceTest.php`). Total 17 tests, 161 assertions passing across the test suite.
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
