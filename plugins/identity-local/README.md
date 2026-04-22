# Identity Local Plugin

Local identity administration plugin for shell-native user and access management.

Current v1 scope:

- local people records backed by `identity_local_users`
- organization memberships backed by the core `memberships` table
- role grant synchronization into `authorization_grants`
- optional linkage from local users to functional actors
- shell screens for people and organization access
- API endpoints for listing, creating, updating, and deleting local users under `/api/v1/identity-local/users`
- API endpoints for creating and updating memberships under `/api/v1/identity-local/memberships`

Still out of scope:

- login, password reset, and session UX
- external identity federation
- advanced invitation and lifecycle workflows
