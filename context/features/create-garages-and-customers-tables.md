# Feature: Create Garages and Customers Tables (Option 1: Distinct Tables)

## Overview
Implement two distinct Doctrine ORM entities and database tables to model:
1. **Garages** ("My clients" / Tenants): Auto repair workshops using the platform.
2. **Customers** ("Their clients"): Vehicle owners / clients belonging to a specific garage.

## Specifications

### Entity 1: `Garage`
- Table: `garage`
- Fields:
  - `id`: `integer` (auto-increment primary key)
  - `name`: `string(255)`, not null
  - `vatNumber`: `string(50)`, nullable (ΑΦΜ)
  - `email`: `string(255)`, not null, unique
  - `phone`: `string(50)`, nullable
  - `address`: `text`, nullable
  - `subscriptionStatus`: `string(50)`, not null (e.g., 'active', 'trial', 'suspended')
  - `createdAt`: `datetime_immutable`, not null
  - `updatedAt`: `datetime_immutable`, nullable
  - Relations:
    - `customers`: `OneToMany` relation to `Customer` (mappedBy: `garage`, cascade: ["remove"], orphanRemoval: true)

### Entity 2: `Customer`
- Table: `customer`
- Fields:
  - `id`: `integer` (auto-increment primary key)
  - `garage`: `ManyToOne` relation to `Garage` (inversedBy: `customers`, nullable: false)
  - `firstName`: `string(100)`, not null
  - `lastName`: `string(100)`, not null
  - `phone`: `string(50)`, not null
  - `email`: `string(255)`, nullable
  - `notes`: `text`, nullable
  - `createdAt`: `datetime_immutable`, not null
  - `updatedAt`: `datetime_immutable`, nullable

### Database & Migrations
- Generate migration via `bin/console make:migration`.
- Execute migration via `bin/console doctrine:migrations:migrate`.

### Testing
- Service/Kernel test (`tests/Entity/GarageCustomerPersistenceTest.php`) verifying:
  - Garage entity creation and persistence.
  - Customer entity creation with Garage association.
  - Cascading deletion of customers when garage is removed.
