# Feature: Create Messaging Schema (Notifications & SMS Templates)

## Overview
Implement the messaging data schema using **UUIDv7** primary keys to power the application's core identity as an **SMS / Viber messaging & garage notification tool**.
This feature establishes two essential entities:
1. **`Notification`**: An audit and dispatch log tracking every outbound message sent or drafted (Quotes, Ready alerts, Service & KTEO reminders) via Native Deep Links (`sms:`, `viber:`) or EasySMS API.
2. **`SmsTemplate`**: Workshop-customizable message templates supporting dynamic variables (`{first_name}`, `{plate}`, `{price}`, `{garage_name}`, etc.).

## Prerequisites
- `symfony/uid` is installed and configured for native UUIDv7 support.
- `Garage`, `Customer`, `Vehicle`, and `WorkOrder` entities exist with UUIDv7 identifiers.

## Specifications

### Entity 1: `Notification`
- **Table**: `notification`
- **Class**: `App\Entity\Notification`
- **Primary Key**: `id` (`uuid`, UUIDv7 generated via `doctrine.uuid_generator`)
- **Fields**:
  - `type`: `string(50)`, not null (Category: `'quote'`, `'ready_for_pickup'`, `'service_reminder'`, `'kteo_reminder'`, `'custom'`)
  - `channel`: `string(20)`, not null (Channel: `'sms'`, `'viber'`, `'native_device'`)
  - `recipientPhone`: `string(50)`, not null (Target mobile phone number)
  - `messageBody`: `text`, not null (The compiled message text)
  - `status`: `string(30)`, not null, default: `'sent'` (Lifecycle: `'pending'`, `'sent'`, `'delivered'`, `'failed'`)
  - `provider`: `string(50)`, not null, default: `'native_deep_link'` (Gateway: `'native_deep_link'`, `'easysms'`)
  - `providerMessageId`: `string(100)`, nullable (EasySMS message reference ID)
  - `cost`: `decimal(6, 4)`, nullable (SMS credit or EUR cost)
  - `sentAt`: `datetime_immutable`, nullable
  - `deliveredAt`: `datetime_immutable`, nullable
  - `errorMessage`: `text`, nullable
  - `createdAt`: `datetime_immutable`, not null (Initialized to current timestamp)
- **Relations**:
  - `garage`: `ManyToOne` to `Garage` (inversedBy: `notifications`, nullable: false, onDelete: "CASCADE")
  - `customer`: `ManyToOne` to `Customer` (inversedBy: `notifications`, nullable: false, onDelete: "CASCADE")
  - `vehicle`: `ManyToOne` to `Vehicle` (inversedBy: `notifications`, nullable: true, onDelete: "SET NULL")
  - `workOrder`: `ManyToOne` to `WorkOrder` (inversedBy: `notifications`, nullable: true, onDelete: "SET NULL")
- **Relation Updates on Existing Entities**:
  - `Garage`: add `notifications` (`OneToMany` to `Notification`, mappedBy: `garage`, cascade: ["remove"], orphanRemoval: true)
  - `Customer`: add `notifications` (`OneToMany` to `Notification`, mappedBy: `customer`, cascade: ["remove"], orphanRemoval: true)
  - `Vehicle`: add `notifications` (`OneToMany` to `Notification`, mappedBy: `vehicle`)
  - `WorkOrder`: add `notifications` (`OneToMany` to `Notification`, mappedBy: `workOrder`)
- **Indexes**:
  - `INDEX idx_notification_garage_created_at (garage_id, created_at)`: Chronological workshop dispatch timeline
  - `INDEX idx_notification_garage_type (garage_id, type)`: Fast category filtering (quotes vs reminders)
  - `INDEX idx_notification_customer_created_at (customer_id, created_at)`: Customer communication history
  - `INDEX idx_notification_work_order (work_order_id)`: Repair-specific message timeline

### Entity 2: `SmsTemplate`
- **Table**: `sms_template`
- **Class**: `App\Entity\SmsTemplate`
- **Primary Key**: `id` (`uuid`, UUIDv7 generated via `doctrine.uuid_generator`)
- **Fields**:
  - `type`: `string(50)`, not null (`'quote'`, `'ready_for_pickup'`, `'service_reminder'`, `'kteo_reminder'`, `'custom'`)
  - `channel`: `string(20)`, not null, default: `'all'` (`'sms'`, `'viber'`, `'all'`)
  - `title`: `string(100)`, not null (e.g. "Ειδοποίηση Ετοιμότητας (SMS)")
  - `body`: `text`, not null (e.g. "Το όχημα {plate} είναι έτοιμο. Κόστος: {price}€. Συνεργείο {garage_name}.")
  - `isActive`: `boolean`, not null, default: `true`
  - `createdAt`: `datetime_immutable`, not null (Initialized to current timestamp)
  - `updatedAt`: `datetime_immutable`, nullable
- **Relations**:
  - `garage`: `ManyToOne` to `Garage` (inversedBy: `smsTemplates`, nullable: false, onDelete: "CASCADE")
- **Relation Updates on Existing Entities**:
  - `Garage`: add `smsTemplates` (`OneToMany` to `SmsTemplate`, mappedBy: `garage`, cascade: ["remove"], orphanRemoval: true)
- **Indexes**:
  - `INDEX idx_sms_template_garage_type (garage_id, type)`: Fast template lookup by event type
  - `INDEX idx_sms_template_garage_is_active (garage_id, is_active)`: Active templates per workshop

### Database & Migrations
- Generate migration via `docker compose exec -T php bin/console make:migration`.
- Execute migration via `docker compose exec -T php bin/console doctrine:migrations:migrate --no-interaction`.
- Execute migration on test environment: `docker compose exec -T php bin/console doctrine:migrations:migrate --env=test --no-interaction`.

### Testing
- Integration tests in `tests/MessagingPersistenceTest.php` verifying:
  - `Notification` persistence with UUIDv7, defaults, relations, and nullability of vehicle/workOrder.
  - `SmsTemplate` persistence with UUIDv7, defaults, and relations to `Garage`.
  - Cascading deletion when related `Garage` or `Customer` is deleted.
  - Nullification (`SET NULL`) when related `Vehicle` or `WorkOrder` is deleted.
  - Parent collection operations on `Garage`, `Customer`, `Vehicle`, and `WorkOrder`.
