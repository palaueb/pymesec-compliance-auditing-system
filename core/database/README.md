# Database

Core-owned persistence artifacts belong here.

Core migrations must remain limited to shared platform entities and infrastructure concerns.

Seeder profiles:

- `SystemBootstrapSeeder` for minimal install-safe bootstrap data
- `DemoCompanySeeder` for local preview and demo workspaces
- `TestDatabaseSeeder` for automated tests

`DatabaseSeeder` selects the default profile through `APP_INSTALL_PROFILE`.
