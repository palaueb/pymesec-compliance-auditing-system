# Plugins

The `plugins/` directory contains plugin-delivered capabilities.

Official plugins shipped in this repository are licensed under `AGPL-3.0-or-later`, aligned with the repository root license.

If third-party plugins are published separately, they must declare their own license in `plugin.json`. This repository does not grant any special linking or proprietary plugin exception.

Plugin categories may include:

- identity
- domain-actor
- domain
- framework-pack
- connector
- reporting
- automation
- ui

Each plugin should remain self-contained with its own manifest, translations, migrations, and source tree.

Minimal recommended structure:

- `plugin.json`
- `src/`
- `routes/`
- `config/`
- `database/migrations/`
- `resources/lang/`
- `README.md`
