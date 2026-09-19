# Current Feature

- **Feature Name**: `create-garages-and-customers-tables`
- **Branch**: `feature/create-garages-and-customers-tables`
- **Status**: Active (Ready for Implementation)
- **Specification**: [create-garages-and-customers-tables.md](file:///home/clickdrive/Desktop/api/backendGarage/context/features/create-garages-and-customers-tables.md)

## Goals
1. Scaffold `Garage` entity and repository with maker bundle.
2. Scaffold `Customer` entity and repository with maker bundle, including `ManyToOne` relationship to `Garage`.
3. Generate Doctrine migration using `bin/console make:migration`.
4. Apply migration using `bin/console doctrine:migrations:migrate`.
5. Generate and execute persistence tests via `bin/console make:test` and `bin/phpunit`.

## Notes
- Strict adherence to AGENTS.md: Use MakerBundle (`docker compose exec -T php bin/console make:...`) without manual PHP scaffolding.
- Request user permission before any manual edits if maker bundle cannot accomplish specific logic.
