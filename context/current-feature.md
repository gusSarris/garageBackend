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
- **create-garages-and-customers-tables** (Completed: 2026-09-20):
  - Added `symfony/uid` via Flex for native UUIDv7 support.
  - Scaffolded `Garage` and `Customer` entities and repositories via MakerBundle with UUIDv7 primary keys.
  - Configured `ManyToOne` (Customer -> Garage) and `OneToMany` (Garage -> Customer) with `orphanRemoval: true` and cascade remove.
  - Added composite indexes on Customer table: `(garage_id, phone)`, `(garage_id, last_name)`, `(garage_id, created_at)`.
  - Generated and executed migration `Version20260920060134`.
  - Implemented persistence integration tests (`tests/GarageCustomerPersistenceTest.php`) verifying UUIDv7, B2B fields, and cascade deletion. 3 tests, 26 assertions passing.
