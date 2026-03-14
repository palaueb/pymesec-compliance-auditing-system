# ADR-018 Installation Profiles and Seeding Strategy

## Status

Accepted

## Context

The repository now serves three distinct runtime scenarios:

1. local development with rich demo data
2. automated tests with deterministic fixture data
3. fresh installations where the platform must start empty and be completed from the web setup wizard

The previous `DatabaseSeeder` behavior mixed these concerns by loading the demo workspace by default. That made local preview easy, but it was wrong for a production-style first installation and blurred the boundary between demo data and test fixtures.

## Decision

The repository adopts explicit installation profiles:

- `system`
- `demo`
- `test`

The seed strategy is defined as follows:

- `SystemBootstrapSeeder` seeds only platform bootstrap data required by the core itself
  - current scope: authorization roles and role-permission relations
- `DemoCompanySeeder` composes the full developer/demo dataset
  - system bootstrap
  - demo tenancy and domain records
  - demo local identity
  - demo LDAP connector state
- `TestDatabaseSeeder` composes the fixture dataset used by automated tests
  - it is explicit and isolated from runtime defaults even if it currently reuses parts of the demo fixture set
- `DatabaseSeeder` resolves the profile from `APP_INSTALL_PROFILE`
  - `system` is the default
  - `demo` is opt-in
  - `test` is reserved for automated execution

The first-run setup wizard is now responsible for creating the first organization when the system profile contains no tenant data.

## Consequences

Positive:

- fresh installations no longer receive demo tenants or demo users by default
- tests no longer depend on the implicit behavior of the runtime `DatabaseSeeder`
- developer environments can still be reset into a rich demo state on demand
- the installation model aligns with the product direction of a usable web-admin flow

Tradeoffs:

- there is now more than one seeder entry point to understand
- the test fixture dataset is still close to the demo dataset and may be slimmed down later

Follow-up:

- add a dedicated developer reset command around the `demo` profile
- continue reducing test fixtures toward minimal deterministic data where useful
- move more first-install configuration into the setup wizard and admin UI
