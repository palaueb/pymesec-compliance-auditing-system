# Identity Local Plugin

Skeleton for the local identity plugin.

Intended responsibilities:

- local authentication implementation
- local user records
- memberships management integration
- mapping authenticated subjects into core principal abstractions

This is a structural placeholder only.
Identity plugin skeleton aligned with ADR-003.

Current scope:

- declares itself as an `identity` plugin
- implements the core identity plugin contract
- contributes permission metadata through `plugin.json`

Still intentionally missing:

- authentication flows
- principal persistence
- membership provisioning
