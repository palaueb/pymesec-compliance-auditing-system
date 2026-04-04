# External Collaboration Security Hardening v1

## Goal

Define a practical hardening baseline for deployments that expose external collaboration portals on the Internet.

This document is operational guidance. It does not replace legal terms, hosting contracts, or organization-specific security controls.

## Scope

Applies to:

- external collaborator links
- external questionnaire answering flows
- external evidence uploads
- outbound invitation delivery paths

## Runtime Security Model

External collaboration access is validated at request time:

- token hash match
- owner component + subject scope match
- link revocation/expiry state
- collaborator lifecycle state (`active` / `blocked`)
- per-link capability flags (questionnaire answers, uploads)

Blocking a collaborator is immediate and does not depend on CRON.

## Deployment Baseline (Mandatory)

1. TLS everywhere:
- enforce HTTPS with HSTS
- disable plain HTTP access

2. Secret handling:
- keep `APP_KEY` and mail credentials out of source control
- rotate secrets when environments are cloned or leaked

3. Access boundaries:
- expose only required public routes
- keep internal admin/workspace routes behind authenticated sessions

4. Upload hardening:
- enforce server-side size/type validation
- keep executable extensions blocked for artifact uploads
- store uploads outside web-executable paths

5. Database and backups:
- encrypt backups at rest
- restrict DB access to application hosts only

6. Operational logging:
- keep audit logs enabled
- monitor failed delivery and repeated invalid token access patterns

## Recommended Controls

- add WAF and bot/rate controls at edge
- add anomaly alerts for external portal traffic spikes
- add periodic review of active external collaborators and links
- add retention cleanup policy for expired/revoked external links

## Scheduling Guidance

No scheduler is required for access correctness.

Optional scheduler jobs improve operations:

- reminder notifications before expiry
- lifecycle posture digest for operators
- retention cleanup/archive jobs

## Product HELP Requirement

Whenever external collaboration behavior changes, update:

- technical specs in `docs/specs`
- in-application HELP concepts for collaboration and third-party-risk

Feature work is not complete until both are updated.
