# Feature: Create Work Orders Table

## Overview
Implement the `WorkOrder` Doctrine ORM entity and database table using **UUIDv7** primary keys.
The application is an **SMS / Viber messaging & garage notification tool** designed for zero-friction workshop operations.
A Work Order (Καρτέλα Εργασίας / Επισκευή) tracks the active lifecycle of a vehicle inside the shop and powers customer communication:
1. **Quote SMS / Viber**: Sends repair cost estimate (`price`) to customer when awaiting approval (`waiting_customer`).
2. **Ready for Pickup SMS / Viber**: Sends ready alert with total cost (`price`) upon job completion (`completed`).
3. **Service & Odometer Tracking**: Captures `odometerKm` at check-in to auto-update next service dates upon delivery.

*Note: No accounting/cash desk/invoicing fields (`paymentStatus`, `paidAmount`, `remainingBalance`, `taxes`) are needed.*

## Prerequisites
- `symfony/uid` is installed and configured for native UUIDv7 support.
- `Garage`, `Customer`, and `Vehicle` entities exist with UUIDv7 identifiers.

## Specifications

### Entity: `WorkOrder`
- **Table**: `work_order`
- **Primary Key**: `id` (`uuid`, UUIDv7 generated via `doctrine.uuid_generator`)
- **Fields**:
  - `date`: `date_immutable`, not null (Ημερομηνία επίσκεψης/καταγραφής)
  - `status`: `string(50)`, not null, default: `'checked_in'` (Lifecycle: 'scheduled', 'checked_in', 'active', 'waiting_parts', 'waiting_customer', 'completed')
  - `scheduledTime`: `string(10)`, nullable (Ώρα προγραμματισμένου ραντεβού, π.χ. "10:30")
  - `description`: `text`, not null (Περιγραφή εργασιών / συμπτωμάτων / chips εργασίας)
  - `price`: `decimal(10, 2)`, not null, default: `0.00` (Κόστος επισκευής που ενσωματώνεται στα μηνύματα SMS/Viber)
  - `odometerKm`: `integer`, nullable (Χιλιόμετρα κοντέρ κατά την παραλαβή)
  - `notes`: `text`, nullable (Εσωτερικές τεχνικές σημειώσεις μάστορα)
  - `partsNotes`: `text`, nullable (Σημειώσεις παραγγελίας ανταλλακτικών όταν είναι σε 'waiting_parts')
  - `checkedInAt`: `datetime_immutable`, nullable (Timestamp παραλαβής στη ράμπα όταν πατηθεί «Ήρθε»)
  - `completedAt`: `datetime_immutable`, nullable (Timestamp ολοκλήρωσης εργασιών)
  - `pickedUpAt`: `datetime_immutable`, nullable (Timestamp παράδοσης στον πελάτη & αρχειοθέτηση)
  - `createdAt`: `datetime_immutable`, not null (Initialized to current timestamp)
  - `updatedAt`: `datetime_immutable`, nullable
- **Relations**:
  - `garage`: `ManyToOne` relation to `Garage` (inversedBy: `workOrders`, nullable: false, onDelete: "CASCADE")
  - `customer`: `ManyToOne` relation to `Customer` (inversedBy: `workOrders`, nullable: false, onDelete: "CASCADE")
  - `vehicle`: `ManyToOne` relation to `Vehicle` (inversedBy: `workOrders`, nullable: false, onDelete: "CASCADE")
- **Relation Updates on Existing Entities**:
  - `Garage`: add `workOrders` (`OneToMany` relation to `WorkOrder`, mappedBy: `garage`, cascade: ["remove"], orphanRemoval: true)
  - `Customer`: add `workOrders` (`OneToMany` relation to `WorkOrder`, mappedBy: `customer`, cascade: ["remove"], orphanRemoval: true)
  - `Vehicle`: add `workOrders` (`OneToMany` relation to `WorkOrder`, mappedBy: `vehicle`, cascade: ["remove"], orphanRemoval: true)
- **Indexes**:
  - `INDEX (garage_id, status)`: Fast filtering of active, queue, and completed jobs in workshop dashboard
  - `INDEX (vehicle_id, date)`: Fast retrieval of vehicle repair and service history
  - `INDEX (customer_id, date)`: Fast retrieval of customer history
  - `INDEX (garage_id, date)`: Fast chronological daily queries

### Database & Migrations
- Generate migration via `docker compose exec -T php bin/console make:migration`.
- Execute migration via `docker compose exec -T php bin/console doctrine:migrations:migrate --no-interaction`.
- Execute migration on test environment: `docker compose exec -T php bin/console doctrine:migrations:migrate --env=test --no-interaction`.

### Testing
- Service/Kernel integration test (`tests/WorkOrderPersistenceTest.php`) verifying:
  - WorkOrder entity creation and persistence with UUIDv7.
  - Associations with `Garage`, `Customer`, and `Vehicle`.
  - Verification of defaults (`status = 'checked_in'`, `price = 0.00`, `createdAt` initialized).
  - Persistence of lifecycle statuses, timestamps (`checkedInAt`, `completedAt`, `pickedUpAt`), `odometerKm`, and notes.
  - Cascading deletion when related Garage, Customer, or Vehicle is removed.
