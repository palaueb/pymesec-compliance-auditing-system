# Plugins

Core plugin lifecycle, registry, manifest parsing, and compatibility infrastructure belongs here.
The `Plugins` namespace contains the minimal plugin runtime for the modular monolith:

- plugin contracts
- manifest parsing
- local discovery and registration
- runtime bootstrapping for enabled plugins

This layer is intentionally simple in v1 and does not yet implement:

- split-repository release automation
- plugin packaging or signed artifacts
- full dependency graphs
- upgrade workflows beyond manifest metadata exposure

Current local lifecycle support:

- discovery from `plugins/<plugin-id>/plugin.json`
- enable/disable overrides persisted in `storage/app/private/plugin-state.json`
- route and permission registration driven by the manifest
- dependency-aware enable/disable safeguards in the lifecycle manager
- optional plugin settings entrypoints declared by `admin.settings_menu_id`
