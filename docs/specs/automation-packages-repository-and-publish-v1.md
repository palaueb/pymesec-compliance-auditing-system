# Automation Packages Repository and Publish v1

## Goal

Define a scalable package ecosystem for automation packs without turning each pack into a Laravel plugin.

The `automation-catalog` plugin remains the single platform plugin.
Automation packs are delivered as external package artifacts.

## Repository Model

`automation-catalog-packages/` is a separate repository.
It may be cloned locally inside the main project for operator convenience and must remain gitignored in this repository.

Repository layout:

```text
automation-catalog-packages/
  src/
    <pack-id>/
      pack.json
      ...
  deploy/
    repository.json
    repository.sign
    <pack-id>/
      pack.json
      <pack-id>-<version>.sign
      <pack-id>-latest.zip
      <pack-id>-<version>.zip
```

## Source Contract

Each source pack lives in `src/<pack-id>/` and must include `pack.json`.

`pack.json` in `src/` is the source-of-truth metadata used by publish scripts.

Minimum source metadata:

- `id`
- `version`
- `name`
- `description`
- `capabilities`
- `config_schema`
- `permissions_requested`
- `entrypoint`

## Publish Pipeline

Publish is generated from `src/` into `deploy/`.

Expected publish behavior:

1. Validate each source `pack.json` against schema.
2. Build zip artifact for each pack version.
3. If `<pack-id>-latest.zip` already exists, preserve previous artifact as `<pack-id>-<previous-version>.zip`.
4. Write new `<pack-id>-latest.zip`.
5. Write/update `deploy/<pack-id>/pack.json` (published metadata snapshot).
6. Rebuild `deploy/repository.json` (global pack index).
7. Sign every published artifact and metadata file.
8. Generate `deploy/repository.sign` for `repository.json`.
9. Generate one signature file per published pack artifact.

`repository.json` must include:

- repository metadata
- each pack id
- latest version
- all published versions per pack (not only latest)
- artifact URLs
- checksums
- signature metadata and signature file locations
- capability and permission summary for operator review

## Catalog Integration

`automation-catalog` configuration supports multiple external package repositories.

Configured as an ordered list of index URLs:

- `repository_url_1`
- `repository_url_2`
- ...

Refresh operation:

- equivalent to an `apt update` behavior
- fetch all configured `repository.json`
- fetch and validate `repository.sign` before accepting repository metadata
- merge entries by priority and version policy
- keep provenance and source URL for every discovered pack

Install operation:

1. Resolve pack/version from merged index.
2. Verify repository signature and artifact signature/checksum policy.
3. Download artifact zip.
4. Register installation metadata in `automation_packs`.
5. Require explicit enable action.

## Versioning and Rollback

Required artifact naming:

- `-latest` for current active artifact
- explicit semantic version artifact for immutable history
- all historical versions must remain discoverable in `repository.json`

Rollback support:

- operator can choose a previous explicit version from local cache/index
- `latest` must never be treated as immutable history

## Runtime Separation

Packs are not Laravel plugins and are not loaded through the plugin manifest/runtime class mechanism.

They are runtime automation artifacts governed by `automation-catalog`.

## Pending Delivery Tasks

- Add install/download/verification flow for package artifacts.
- Add pack version selection and rollback UI.

## Current Implementation Notes

Implemented in the current wave:

- `automation-catalog` plugin supports repository registration and signed index refresh.
- Local packages workspace is initialized at `automation-catalog-packages/` (gitignored in the main repository).
- Publish script available: `automation-catalog-packages/publish.php`.
- First sample pack available: `automation-catalog-packages/src/utility.hello-world/`.

Still pending:

- Runtime artifact install flow (`download -> verify -> runtime register`).
- Policy gates at install-time (manifest schema enforcement and static inspection) in platform flow.
