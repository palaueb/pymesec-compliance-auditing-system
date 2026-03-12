# Plugins

The `plugins/` directory contains plugin-delivered capabilities.

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
