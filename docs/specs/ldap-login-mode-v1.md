# LDAP Login Mode V1

## Scope

- `identity-ldap` now completes password login on top of the synced local cache instead of stopping at directory sync only.
- The external directory remains the first-factor authority.
- The cached `identity_local_users` record remains the session and authorization anchor.

## Authentication Contract

- `login_mode = username` means LDAP password authentication only resolves the cached LDAP user by `username`.
- `login_mode = email` means LDAP password authentication only resolves the cached LDAP user by `email`.
- After a successful LDAP bind, sign-in continues through the existing email verification challenge used by `identity-local`.
- Completed LDAP password sign-in stores `auth.provider = identity-ldap`.

## Email Fallback Contract

- Cached email-link sign-in for LDAP-backed users is governed by the live connector flag `fallback_email_enabled`.
- Disabling fallback on the connector blocks new LDAP magic links immediately, even before the next sync refreshes cached user flags.
- LDAP magic-link sessions also resolve to `auth.provider = identity-ldap`.

## Failure Rules

- If the connector is disabled, LDAP password login does not fall through to cached password access.
- If the cached LDAP record is inactive or missing, sign-in is denied even when directory credentials are otherwise valid.
- Local password and magic-link flows continue to operate only on local accounts; they no longer act as an accidental fallback path for LDAP-backed users.
