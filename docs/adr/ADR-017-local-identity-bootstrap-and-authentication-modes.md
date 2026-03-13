# Title

ADR-017: Mandatory Local Identity Fallback, Bootstrap Wizard, and Authentication Modes

# Status

Accepted

# Context

The platform already separates access identity from functional actors in ADR-003 and keeps the core identity-provider-agnostic. The next architectural step is to define how authentication works in practice when:

- the product must always retain a local administrative fallback
- organizations may later connect one external LDAP directory
- local users, synchronized users, and emergency users must coexist
- initial installation must be operable before external identity is configured

The product decisions established so far are:

- `identity-local` is mandatory and always enabled
- every user record must carry both `username` and `email`
- local users may authenticate with password, but password access must require a second email step
- email link access must remain available where explicitly enabled
- the system must expose a first-run wizard when no user exists yet
- the first implemented external connector path will be LDAP

# Decision

The platform will use `identity-local` as the mandatory canonical identity runtime.

`identity-local` is responsible for:

- the persistent local user catalog
- the canonical principal mapping used by the core
- bootstrap of the first administrative account
- local authentication sessions
- email-based second-factor or passwordless flows
- fallback access when external identity is unavailable

Future external identity plugins such as `identity-ldap` will not replace the canonical local runtime. They will synchronize identities into the local catalog and delegate final session creation to the same local login runtime.

## User Record Requirements

Every access user must have:

- a unique `username`
- a unique `email`
- a canonical `principal_id`

This applies to local users and to future synchronized LDAP users.

## Local Authentication Modes

`identity-local` supports these modes:

- `password`
- `mail-login-token`

Each local user may enable one or both modes.

When `password` is enabled:

- the first factor is `username` or `email` plus password
- the second factor is an email-delivered one-time code
- password-only access is not allowed

When `mail-login-token` is enabled:

- the user may request a secure email sign-in link

The default first-run posture is:

- email sign-in link enabled
- password optional

## LDAP Direction

The first external identity implementation path is LDAP only.

LDAP users must still be stored locally with `username`, `email`, and `principal_id`.

LDAP will later support:

- login identifier mode `username` or `email`
- normal LDAP password authentication
- fallback email-based access when LDAP is unavailable and a synchronized local user cache exists

## Bootstrap Behavior

If the system detects that no local user exists, it must interrupt normal access flow and show a setup wizard.

The setup wizard creates the first local administrator and grants the `platform-admin` role to that principal.

The bootstrap wizard is tied to the mandatory local identity fallback and is therefore allowed to exist as a special-case entry path even though the broader core remains identity-provider-agnostic.

# Consequences

- The product can always recover administrative access without depending on LDAP.
- Authentication behavior becomes explicit and configurable per user rather than being hardcoded to one universal flow.
- Password access gains a minimal second factor without requiring a full TOTP stack in v1.
- The first-run experience becomes operable for self-hosted installs.
- Future LDAP integration can reuse the same session and audit runtime instead of building a separate authentication stack.
- Some core entry routes must be aware of the bootstrap condition because `identity-local` is mandatory by product decision.

# Rejected Alternatives

1. Passwordless-only authentication for every user

Rejected because local and LDAP-backed organizations need a conventional login option, and administrators explicitly want password access to remain possible.

2. Password-only authentication for local users

Rejected because local fallback and directory-failure scenarios still need an email-based route, and the product wants passwordless access to stay available where enabled.

3. External directory as the canonical principal source

Rejected because the platform must keep operating when LDAP is unavailable and because the core permission model already depends on stable local principal references.

4. No bootstrap wizard, requiring manual database seeding for the first admin

Rejected because self-hosted setup needs an application-level first-run path similar to other admin-first systems.

# Open Questions

- How should communication and mail delivery health be exposed in the admin UI before LDAP and second-factor flows are relied on in production?
- Which LDAP group-mapping model should be implemented first for memberships, roles, and scopes?
- Should future non-local users be allowed to disable local email fallback individually, or should fallback remain enforced globally?
