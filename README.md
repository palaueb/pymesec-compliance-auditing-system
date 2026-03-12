# PymeSec

Initial technical bootstrap of the main repository based on the PRD and ADRs.

## License

This repository is licensed under `GNU Affero General Public License v3.0 or later`.

- The monorepo root is covered by [LICENSE](/media/marc/4T_EXFAT/web/pymesec.com/pymesec/LICENSE).
- The `core` declares `AGPL-3.0-or-later` in [core/composer.json](/media/marc/4T_EXFAT/web/pymesec.com/pymesec/core/composer.json).
- The official plugins currently shipped in `plugins/` also declare `AGPL-3.0-or-later` in their manifests.

The practical policy in this repository is simple:

- bundled official plugins are licensed under the same AGPL terms as the core
- external plugins may declare their own license, but compatibility and redistribution obligations depend on how tightly they are combined with the core runtime

This repository does not define any plugin linking exception.

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
