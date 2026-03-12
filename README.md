# PymeSec

Initial technical bootstrap of the main repository based on the PRD and ADRs.

The current state sets up:

- `core/` as the Laravel application for the platform core
- local development with Docker
- minimal local services: `nginx`, `php-fpm`, `postgres`, `redis`
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
```

## Environment Notes

- The `core` uses PostgreSQL and Redis locally.
- The base tests run on in-memory SQLite to keep them fast and isolated.
- The `core` does not yet implement any functional identity model; Laravel's default `User` has been removed to respect the ADRs.

## Documented Temporary Assumptions

- Laravel is installed directly inside `core/` as the main application for the platform core.
- The plugin manifest temporarily remains in `plugin.json` until that decision is finalized.
- Local development is handled with `nginx + php-fpm + postgres + redis`.
- The minimal CI validates installation, migrations, linting, and tests, but not yet plugin compatibility matrices.
