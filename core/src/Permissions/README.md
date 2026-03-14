# Permissions

Core permission, role, grant, and policy abstractions belong here.
Core permission definitions and registration infrastructure defined by ADR-004.

Current scope:

- permission definition model
- registry for core and plugin-declared permissions
- manifest-based registration during plugin bootstrapping
- in-memory roles and grants for authorization evaluation
- authorization result model with `allow`, `deny`, and `unresolved`
- presentation taxonomy used by the shell to distinguish platform, access, and operational workspace roles
