# Core

Laravel bootstrap for the platform core.

This directory must remain:

- minimal
- stable
- domain-agnostic
- independent from any specific identity provider

The `core` contains only platform infrastructure and no functional compliance logic.

Runtime notes:

- `Tenancy` is now manageable from the shell for authorized platform admins
- first install bootstrap is expected to start from the `system` seed profile
- demo and test data are composed through dedicated seeders instead of the default install path
