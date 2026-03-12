# PymeSec

Initial technical bootstrap of the main repository based on the PRD and ADRs.

The current state sets up:

- `core/` as the Laravel application for the platform core
- `plugins/` for independently developable official plugin packages
- local development with Docker
- minimal local services: `apache`, `php`, `mysql`
- working scripts via `Makefile`
- minimal CI to validate installation, linting, and tests

Still out of scope:

- compliance business logic
- implementation of functional plugins beyond their skeletons
- functional product UI

## Prerequisites

- Docker
- Docker Compose
- GNU Make

## Quick Start

1. Copy the root environment configuration:

```bash
cp .env.example .env
```

2. Copy the core configuration:

```bash
cp core/.env.example core/.env
```

3. Start the environment:

```bash
make up
```

4. Run the core migrations:

```bash
make migrate
```

5. Verify that Laravel responds:

```bash
curl http://localhost:8080/up
curl http://localhost:8080/
```

## Common Commands

```bash
make up
make down
make shell
make migrate
make test
make lint
make logs
docker compose exec app php artisan plugins:list
docker compose exec app php artisan menus:list
docker compose exec app php artisan workflows:list
docker compose exec app php artisan plugins:enable identity-local
docker compose exec app php artisan plugins:disable hello-world
```

## Environment Notes

- The `core` uses MySQL locally.
- The application discovers plugins from `plugins/` and computes the effective enabled set from `PLUGINS_ENABLED` plus local overrides stored in `storage/app/private/plugin-state.json`.
- Shell menus are core-governed; plugins may contribute top-level items and one child level through the manifest, subject to route, permission, and dependency validation.
- Local cache defaults to `file` and the queue defaults to `sync`, so Redis is not required in development.
- The base tests run on in-memory SQLite to keep them fast and isolated.
- The `core` does not yet implement any functional identity model; Laravel's default `User` has been removed to respect the ADRs.

## Documented Temporary Assumptions

- Laravel is installed directly inside `core/` as the main application for the platform core.
- The plugin manifest temporarily remains in `plugin.json` until that decision is finalized.
- Local development is handled with `apache + php + mysql`.
- The minimal CI validates installation, migrations, linting, and tests, but not yet plugin compatibility matrices.
