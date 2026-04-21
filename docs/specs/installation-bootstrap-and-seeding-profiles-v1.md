# Title

Installation Bootstrap and Seeding Profiles v1

# Status

Draft

# Context

PymeSec needs to support three different operating modes without mixing their data assumptions:

- fresh installation
- local development with demo data
- automated test execution

The core already has a first-run setup wizard, persistent tenancy, and plugin-driven identity. The remaining requirement is to define how bootstrap data is separated so the same repository can serve all three modes safely.

# Specification

## 1. Profiles

The repository defines three seed profiles:

- `system`
- `demo`
- `test`

`APP_INSTALL_PROFILE` selects the default profile used by `DatabaseSeeder`.

### `system`

Purpose:

- empty or near-empty installation bootstrap
- safe default for real deployments

Includes:

- core bootstrap records required by the platform itself
- no demo organizations
- no demo users
- no demo domain data

### `demo`

Purpose:

- local product exploration
- developer sandboxing
- manual validation of plugin interactions

Includes:

- system bootstrap data
- demo organizations and scopes
- demo memberships and grants
- demo domain records
- demo local and LDAP-backed identities

### `test`

Purpose:

- deterministic automated tests

Includes:

- explicit fixture seeding through `TestDatabaseSeeder`
- fixture data may reuse demo records initially, but the entry point must remain test-specific

## 2. Fresh Installation Flow

When the database contains no local users, the core shell redirects to the setup wizard.

If the system profile also contains no organizations, the wizard must collect:

- organization name
- optional organization slug
- default locale
- default timezone
- first administrator identity

The wizard then creates:

- the first organization
- the first local administrator
- the initial administrative grants required to access tenancy and identity screens

Validation failures must return the user to the setup wizard with visible field errors. Non-sensitive fields should be preserved after validation failure; password fields must stay empty. The first administrator password remains optional, but when supplied it must satisfy the local identity password rules, including the minimum length enforced by the application.

## 3. Developer Workflow

Recommended developer operations:

- `make migrate`
- `make seed-demo`
- `make reset-demo`

`reset-demo` must rebuild the database into a rich demo state suitable for UI exploration.

## 4. Test Workflow

Automated tests must not rely on whichever profile happens to be configured in local runtime.

Test bootstrap rules:

- PHPUnit sets `APP_INSTALL_PROFILE=test`
- base test setup seeds `TestDatabaseSeeder`
- tests remain isolated through `RefreshDatabase`

## 5. Documentation Contract

The following documentation must stay aligned with this behavior:

- root `README.md`
- `docs/adr/ADR-018-installation-profiles-and-seeding-strategy.md`
- `docs/specs/tenancy-model-v1.md`
- `core/database/README.md`
- `core/tests/README.md`

## 6. Future Work

- dedicated developer reset command beyond `make reset-demo`
- slimmer fixture set for tests where the current demo-heavy dataset becomes a maintenance drag
- admin UI for more first-install settings such as communications and plugin lifecycle
