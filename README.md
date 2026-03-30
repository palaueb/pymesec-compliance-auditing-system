# PymeSec

Compliance auditing system for small and medium-sized enterprises (SMEs). Automates security controls, GDPR, ISO 27001, and local compliance.

[![PHP](https://img.shields.io/badge/PHP-^8.1-blue)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/Laravel-^10-orange)](https://laravel.com/)
[![License](https://img.shields.io/github/license/palaueb/pymesec-compliance-auditing-system)](https://github.com/palaueb/pymesec-compliance-auditing-system/blob/main/LICENSE)
[![Lines of Code](https://img.shields.io/endpoint?url=https%3A%2F%2Ftokei.kojix2.net%2Fbadge%2Fgithub%2Fpalaueb%2Fpymesec-compliance-auditing-system%2Flines)](https://tokei.kojix2.net/github/palaueb/pymesec-compliance-auditing-system)
[![Top Language](https://img.shields.io/endpoint?url=https%3A%2F%2Ftokei.kojix2.net%2Fbadge%2Fgithub%2Fpalaueb%2Fpymesec-compliance-auditing-system%2Flanguage)](https://tokei.kojix2.net/github/palaueb/pymesec-compliance-auditing-system)
[![Issues](https://img.shields.io/github/issues/palaueb/pymesec-compliance-auditing-system)](https://github.com/palaueb/pymesec-compliance-auditing-system/issues)
[![Stars](https://img.shields.io/github/stars/palaueb/pymesec-compliance-auditing-system)](https://github.com/palaueb/pymesec-compliance-auditing-system/stargazers)

## Screenshots

![PymeSec Beta Screenshot](https://raw.githubusercontent.com/palaueb/pymesec-compliance-auditing-system/refs/heads/main/docs/screenshots/beta1.png)

![PymeSec Beta Screenshot](https://raw.githubusercontent.com/palaueb/pymesec-compliance-auditing-system/refs/heads/main/docs/screenshots/beta2.png)

![PymeSec Beta Screenshot](https://raw.githubusercontent.com/palaueb/pymesec-compliance-auditing-system/refs/heads/main/docs/screenshots/beta3.png)

## Documentation Index

Primary documentation hubs:

- [docs/README.md](docs/README.md)
- [docs/prd/README.md](docs/prd/README.md)
- [docs/adr/README.md](docs/adr/README.md)
- [docs/specs/README.md](docs/specs/README.md)

Operational READMEs:

- [core/README.md](core/README.md)
- [core/database/README.md](core/database/README.md)
- [core/tests/README.md](core/tests/README.md)
- [plugins/README.md](plugins/README.md)
- [packages/README.md](packages/README.md)

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

5. Choose the data profile you want:

```bash
make seed-system
```

For a developer sandbox with demo data:

```bash
make seed-demo
```

6. Verify that Laravel responds:

```bash
curl http://localhost:8080/up
curl -I http://localhost:8080/
```

Fresh install behavior:

- `system` is the default install profile
- the app starts with no demo tenants or users
- the first visit to `/app` opens the setup wizard and can create the first organization plus superadmin
- `/` is the product entrypoint: if no session exists it redirects to login, and after sign-in it lands in the application workspace
- `/admin` is a separate administration shell for platform settings and core operations

## Common Commands

```bash
make up
make down
make shell
make migrate
make seed-system
make seed-demo
make reset-demo
make test
make lint
make logs
docker compose exec app php artisan plugins:list
docker compose exec app php artisan menus:list
docker compose exec app php artisan workflows:list
docker compose exec app php artisan plugins:enable identity-local
docker compose exec app php artisan plugins:enable identity-ldap
docker compose exec app php artisan plugins:disable hello-world
```
## Email login on test

If you are on development you can check for code and email magic link at command:

``` # /core$ docker compose exec app tail -f storage/logs/laravel.log ```

## LDAP Demo

The repository includes a disposable LDAP directory for testing `identity-ldap`.

Start it with the normal stack:

```bash
make up
```

Available endpoints:

- LDAP server: `localhost:3389`
- phpLDAPadmin: `http://localhost:8081`
- admin bind DN: `cn=admin,dc=northwind,dc=test`
- admin password: `admin`

Seeded demo entries:

- `uid=lars.heidt,ou=People,dc=northwind,dc=test`
- `uid=marta.soler,ou=People,dc=northwind,dc=test`
- groups `cn=it-services,...` and `cn=eu-operations,...`

Connector values:

- if the app runs inside Docker, use host `ldap` and port `389`
- if the app runs outside Docker, use host `127.0.0.1` and port `3389`
- base DN: `ou=People,dc=northwind,dc=test`
- bind DN: `cn=admin,dc=northwind,dc=test`
- login attribute: `uid`
- mail attribute: `mail`
- group attribute: `memberOf`

## Environment Notes

- The `core` uses MySQL locally.
- `APP_INSTALL_PROFILE=system` is the safe default for new installations.
- `DemoCompanySeeder` is opt-in and intended for local exploration.
- automated tests seed `TestDatabaseSeeder` explicitly and do not depend on the runtime default profile.
- The application discovers plugins from `plugins/` and computes the effective enabled set from `PLUGINS_ENABLED` plus local overrides stored in `storage/app/private/plugin-state.json`.
- `Administration > Plugins` can now apply audited enable or disable overrides from the shell, while plugin-specific settings stay owned by each plugin workspace screen.
- Shell menus are core-governed; plugins may contribute top-level items and one child level through the manifest, subject to route, permission, and dependency validation.
- Local cache defaults to `file` and the queue defaults to `sync`, so Redis is not required in development.
- The base tests run on in-memory SQLite to keep them fast and isolated.
- The repository now includes local identity bootstrap plus optional LDAP sync as plugins, while the core remains provider-agnostic.
- The Docker PHP image now ships `docker/php/zz-pymesec.ini` with `memory_limit=512M` plus conservative request-size limits so large route bootstraps and admin screens do not fail under the previous `128M` default.

## Documented Temporary Assumptions

- Laravel is installed directly inside `core/` as the main application for the platform core.
- The plugin manifest temporarily remains in `plugin.json` until that decision is finalized.
- Local development is handled with `apache + php + mysql`.
- The minimal CI validates installation, migrations, linting, and tests, but not yet plugin compatibility matrices.


## License

This repository is licensed under `GNU Affero General Public License v3.0 or later`.

- The monorepo root is covered by [LICENSE](LICENSE).
- The `core` declares `AGPL-3.0-or-later` in [core/composer.json](core/composer.json).
- The official plugins currently shipped in `plugins/` also declare `AGPL-3.0-or-later` in their manifests.

The practical policy in this repository is simple:

- bundled official plugins are licensed under the same AGPL terms as the core
- external plugins may declare their own license, but compatibility and redistribution obligations depend on how tightly they are combined with the core runtime

This repository does not define any plugin linking exception.

The current state includes:

- `core/` as the Laravel application for the platform core
- `plugins/` for independently developable official plugin packages
- local development with Docker
- minimal local services: `apache`, `php`, `mysql`
- optional LDAP demo stack with seeded users and groups
- working scripts via `Makefile`
- minimal CI to validate installation, linting, and tests
- usable shell screens for core administration and current domain plugins
