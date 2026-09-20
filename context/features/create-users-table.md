# Feature: Create Users Table

## Overview
Implement the `User` Doctrine ORM entity and database table using **UUIDv7** primary keys.
The `User` entity represents the shop manager, owner, or mechanic who accesses the workshop application, supporting:
1. **Multi-Tenant Association**: Each user is strictly bound to a `Garage` (`garage_id`) ensuring secure tenant data isolation.
2. **Symfony Security Integration**: Implements `UserInterface` and `PasswordAuthenticatedUserInterface` for secure password hashing and role-based access control (`ROLE_GARAGE_ADMIN`, `ROLE_MECHANIC`, `ROLE_USER`).
3. **Account Management**: Tracks status (`isActive`), contact info (`fullName`, `phone`), and audit timestamps.

*Note: In PostgreSQL, `user` is a reserved keyword. The table will be explicitly named `app_user` to prevent SQL syntax conflicts.*

## Prerequisites
- `symfony/security-bundle` installed via Flex (`composer require symfony/security-bundle`).
- `symfony/uid` installed for native UUIDv7 support.
- `Garage` entity exists with UUIDv7 identifier.

## Specifications

### Entity: `User`
- **Table**: `app_user`
- **Class**: `App\Entity\User`
- **Implements**: `Symfony\Component\Security\Core\User\UserInterface`, `Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface`
- **Primary Key**: `id` (`uuid`, UUIDv7 generated via `doctrine.uuid_generator`)
- **Fields**:
  - `email`: `string(180)`, not null, unique (Login identifier via `getUserIdentifier()`)
  - `roles`: `json`, not null, default: `["ROLE_MECHANIC"]` (Always includes `ROLE_USER` at runtime)
  - `password`: `string(255)`, not null (Hashed credentials)
  - `fullName`: `string(150)`, not null (Ονοματεπώνυμο μηχανικού / διαχειριστή)
  - `phone`: `string(50)`, nullable (Προσωπικό ή επαγγελματικό κινητό χρήστη)
  - `isActive`: `boolean`, not null, default: `true` (Ενεργός λογαριασμός)
  - `createdAt`: `datetime_immutable`, not null (Initialized to current timestamp)
  - `updatedAt`: `datetime_immutable`, nullable
- **Relations**:
  - `garage`: `ManyToOne` relation to `Garage` (inversedBy: `users`, nullable: false, onDelete: "CASCADE")
- **Relation Updates on Existing Entities**:
  - `Garage`: add `users` (`OneToMany` relation to `User`, mappedBy: `garage`, cascade: ["remove"], orphanRemoval: true)
- **Indexes**:
  - `UNIQUE INDEX uniq_user_email (email)`: Enforces unique login email
  - `INDEX idx_user_garage_id (garage_id)`: Fast multi-tenant user resolution
  - `INDEX idx_user_garage_is_active (garage_id, is_active)`: Fast querying of active staff per garage

### Database & Migrations
- Generate migration via `docker compose exec -T php bin/console make:migration`.
- Execute migration via `docker compose exec -T php bin/console doctrine:migrations:migrate --no-interaction`.
- Execute migration on test environment: `docker compose exec -T php bin/console doctrine:migrations:migrate --env=test --no-interaction`.

### Testing
- Service/Kernel integration test (`tests/UserPersistenceTest.php`) verifying:
  - User creation and persistence with UUIDv7 identifier.
  - Association with `Garage` and tenant relationship.
  - Verification of defaults (`isActive = true`, `createdAt` initialized, default roles).
  - Proper implementation of `UserInterface` methods (`getUserIdentifier`, `getRoles`, `eraseCredentials`).
  - Password hash storage and retrieval.
  - Cascading deletion when the parent `Garage` is removed.
