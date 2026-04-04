# Automation Pack Security Policy v1

## Goal

Define a practical security model for automation packs in real PHP hosting environments.

This policy explicitly avoids requiring container runtime as a hard dependency, while still enforcing strong guardrails.

## Threat Model

Main risks:

- malicious or compromised pack source
- pack code performing behavior outside declared scope
- unsafe filesystem, process, or network operations
- credential leakage across packs
- hidden privileged operations not visible to operators

## Security Principles

- deny-by-default capabilities
- explicit operator trust decision per source/repository
- mandatory pre-install validation
- no direct DB access for pack code
- per-pack secret isolation
- full audit trail and immediate kill switch

## Install-Time Policy Gates

Before install, a pack must pass:

1. Metadata schema validation (`pack.json`).
2. Integrity checks (checksum; signature where configured).
3. License and provenance checks.
4. Static inspection gate.
5. Operator approval of requested permissions/capabilities.

## Static Inspection Gate

Static inspection is mandatory but not considered sufficient as a standalone control.

Minimum checks:

- forbidden function usage (`exec`, `shell_exec`, `passthru`, `proc_open`, `popen`)
- forbidden socket/server primitives (`socket_*`, `stream_socket_server`)
- forbidden dynamic code execution patterns (`eval`, runtime include from remote input)
- forbidden unpack/obfuscation patterns (`base64_decode`, `gzinflate`, `gzdecode`, `str_rot13`, similar decode chains)
- forbidden file write patterns outside allowed runtime workspace
- forbidden direct database client usage in pack code

Packs MUST remain plain PHP source code.
Obfuscated, packed, or encoded delivery formats are not allowed.

If static inspection cannot classify a construct, policy defaults to fail/require manual override based on configured trust level.

## Runtime Execution Model

### Mode A: Standard PHP Hosting (baseline)

This is the only supported runtime mode.

Required controls:

- execution triggered only by CRON schedules or explicit manual runs from the platform
- no ad-hoc per-pack runtime profile changes through custom php.ini files
- no extra memory overrides beyond known hosting limits
- restricted writable workspace
- no direct access to core source paths outside runner workspace
- no direct DB credentials exposed to pack code

External software may run in containers outside PymeSec and expose a controlled API.
Packs may consume that API if capabilities and network targets are explicitly approved.

## Capability and Permission Model

Pack metadata must declare:

- connector targets (service families/domains)
- output capabilities (evidence refresh, workflow transition, etc.)
- runtime permissions requested
- config and secret requirements

Execution is brokered by `automation-catalog`:

- pack receives scoped execution context
- allowed operations are checked against declared and approved capabilities
- undeclared operations are denied and audited

## Secrets and Configuration

Pack config forms are generated from metadata (`config_schema`).

Secret handling requirements:

- encrypted at rest
- key derivation scoped by organization + pack id
- no pack-to-pack secret access
- no plaintext secret exposure in logs or audit summaries

## Operator Trust Tiers

Recommended trust tiers:

- `trusted-first-party`
- `trusted-partner`
- `community-reviewed`
- `untrusted`

Policy behavior can vary by tier (for example: strict signature required for non-first-party packs).

## Kill Switch, Revocation, and Audit

Mandatory controls:

- immediate disable of one pack
- optional disable of all packs from one repository
- revoke active execution tokens
- preserve run/install/update/disable audit history

Audit minimum:

- who installed/enabled/disabled
- source repository and version
- requested vs granted capabilities
- run status and failure reason

## Known Limitations

Static analysis cannot prove full runtime safety for arbitrary PHP code.

Therefore this policy relies on layered controls:

- repository trust and signing
- static inspection
- capability broker
- restricted runtime profile
- operator governance and auditability

## Pending Delivery Tasks

- Implement pack inspector command and policy report output.
- Implement capability broker enforcement in runtime runner.
- Implement per-pack encrypted config storage and generated forms.
- Implement trust-tier policy profiles in automation settings UI.
