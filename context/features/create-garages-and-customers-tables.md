# Feature: Create Garages and Customers Tables

## Overview
Implement two distinct Doctrine ORM entities and database tables using **UUIDv7** primary keys:
1. **Garages** ("My clients" / Tenants): Auto repair workshops using the platform.
2. **Customers** ("Their clients"): Vehicle owners or business fleets belonging to a specific garage.

## Prerequisites
- Install `symfony/uid` via Flex: `docker compose exec -T php composer require symfony/uid` to enable native UUIDv7 support in Doctrine ORM.

## Specifications

### Entity 1: `Garage`
- **Table**: `garage`
- **Fields**:
  - `id`: `uuid` (UUIDv7, primary key)
  - `name`: `string(255)`, not null (Workshop business/trade name)
  - `vatNumber`: `string(50)`, nullable (Tax ID / ΑΦΜ)
  - `taxOffice`: `string(100)`, nullable (Tax Office / ΔΟΥ)
  - `email`: `string(255)`, not null, unique
  - `phone`: `string(50)`, nullable (Primary workshop phone)
  - `address`: `string(255)`, nullable (Street name and number)
  - `city`: `string(100)`, nullable (City / Municipality)
  - `postalCode`: `string(20)`, nullable (Postal code)
  - `subscriptionStatus`: `string(50)`, not null, default: `'trial'` (e.g. 'trial', 'active', 'suspended', 'cancelled')
  - `isActive`: `boolean`, not null, default: `true`
  - `createdAt`: `datetime_immutable`, not null (Initialized to current time)
  - `updatedAt`: `datetime_immutable`, nullable
- **Relations**:
  - `customers`: `OneToMany` relation to `Customer` (mappedBy: `garage`, cascade: ["remove"], orphanRemoval: true)

### Entity 2: `Customer`
- **Table**: `customer`
- **Fields**:
  - `id`: `uuid` (UUIDv7, primary key)
  - `garage`: `ManyToOne` relation to `Garage` (inversedBy: `customers`, nullable: false)
  - `firstName`: `string(100)`, nullable (Individual customer first name)
  - `lastName`: `string(100)`, nullable (Individual customer last name)
  - `companyName`: `string(255)`, nullable (Company/Fleet name for B2B accounts)
  - `vatNumber`: `string(50)`, nullable (Customer tax ID / ΑΦΜ for commercial invoicing)
  - `taxOffice`: `string(100)`, nullable (Customer tax office / ΔΟΥ)
  - `phone`: `string(50)`, not null (Primary contact number for SMS/calls)
  - `secondaryPhone`: `string(50)`, nullable (Alternate phone / landline)
  - `email`: `string(255)`, nullable
  - `address`: `string(255)`, nullable
  - `city`: `string(100)`, nullable
  - `postalCode`: `string(20)`, nullable
  - `notes`: `text`, nullable
  - `createdAt`: `datetime_immutable`, not null (Initialized to current time)
  - `updatedAt`: `datetime_immutable`, nullable
- **Indexes**:
  - `INDEX (garage_id, phone)`: Fast lookup when customer calls or visits
  - `INDEX (garage_id, last_name)`: Fast autocomplete search
  - `INDEX (garage_id, created_at)`: Fast listing of recent registrations

### Database & Migrations
- Generate migration via `bin/console make:migration`.
- Execute migration via `bin/console doctrine:migrations:migrate`.

### Testing
- Service/Kernel test (`tests/Entity/GarageCustomerPersistenceTest.php`) verifying:
  - Garage entity creation and persistence with UUIDv7.
  - Customer entity creation with Garage association and UUIDv7.
  - Verification of nullable B2B fields (company name, VAT).
  - Cascading deletion of customers when garage is removed.