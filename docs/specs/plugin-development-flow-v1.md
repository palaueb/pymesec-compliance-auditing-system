# Title

Plugin Development Flow v1

# Status

Draft

# Purpose

Define the smallest supported development flow for a plugin in the coordinated main repository, without implementing full packaging or release automation yet.

# Minimal Plugin Layout

Each plugin should live under `plugins/<plugin-id>/` and keep its assets self-contained.

Minimal layout:

- `plugin.json`: authoritative plugin metadata and runtime declaration
- `src/`: plugin runtime classes
- `routes/`: optional Laravel route files owned by the plugin
- `config/`: optional plugin-local configuration files
- `database/migrations/`: optional plugin-local migrations
- `resources/lang/`: optional translations
- `README.md`: local plugin development notes

# Runtime Contract

In the current skeleton, the core plugin manager works like this:

1. scan every configured plugin directory for `plugin.json`
2. parse manifest metadata
3. mark the plugin as discovered
4. compute the effective enabled set from `PLUGINS_ENABLED` plus local state overrides
5. validate the declared core compatibility range
6. instantiate the runtime class declared in `runtime.class`
7. register manifest-declared permissions
8. load manifest-declared route files
9. register manifest-declared menu entries
10. call `register()`
11. call `boot()`

The runtime class must implement `PymeSec\Core\Plugins\Contracts\PluginInterface`.

Minimal example:

```php
<?php

namespace Vendor\Plugin;

use PymeSec\Core\Plugins\Contracts\PluginInterface;
use PymeSec\Core\Plugins\PluginContext;

final class ExamplePlugin implements PluginInterface
{
    public function register(PluginContext $context): void
    {
        //
    }

    public function boot(PluginContext $context): void
    {
        //
    }
}
```

# Minimal `plugin.json`

Current skeleton expects the existing manifest sections plus a temporary runtime declaration for local development:

```json
{
  "plugin": {
    "id": "example-plugin",
    "name": "Example Plugin",
    "version": "0.1.0",
    "type": "ui"
  },
  "compatibility": {
    "core": "^0.3.0"
  },
  "routes": [
    {
      "id": "example-plugin.web",
      "type": "web",
      "file": "routes/web.php"
    }
  ],
  "runtime": {
    "class": "Vendor\\Plugin\\ExamplePlugin",
    "autoload": {
      "psr-4": {
        "Vendor\\Plugin\\": "src"
      }
    }
  }
}
```

Notes:

- `runtime` is intentionally minimal and local-development-oriented.
- `routes[].file` is currently the local-development hook used by the core to load plugin route groups from the plugin package.
- `PLUGINS_ENABLED` defines the base enabled set; local CLI overrides are stored in `storage/app/private/plugin-state.json`.
- The existing manifest spec can evolve later without breaking this skeleton because unknown fields are already tolerated.
- A plugin can exist with only a manifest and no runtime class; in that case it is discovered but not booted.

# Flow To Create a New Plugin

1. Create `plugins/<plugin-id>/`.
2. Add `plugin.json` with at least `plugin`, `compatibility`, and `runtime`.
3. Add the runtime class under `src/` implementing `PluginInterface`.
4. Add optional route, menu, config, migration, and translation assets inside the plugin directory.
5. Enable the plugin through `PLUGINS_ENABLED` or `php artisan plugins:enable <plugin-id>`.
6. Verify discovery at `/core/plugins`.
7. If the plugin type is `identity`, implement `IdentityPluginInterface`.
8. If the plugin type is `domain-actor`, implement `FunctionalActorPluginInterface`.
9. Declare plugin permissions in `plugin.json` using the `plugin.<plugin-id>.*` naming prefix.
10. If the plugin reacts to public events, subscribe through `PluginContext::subscribeToEvent(...)` and keep payload expectations namespaced and explicit.
11. Verify discovery at `/core/plugins`, permission registration at `/core/permissions`, menu registration at `/core/menus`, and plugin behavior through routes, events, or tests.

# Deliberately Out of Scope

This v1 skeleton does not yet implement:

- plugin installation and uninstallation workflows
- dependency graphs between plugins
- plugin-specific migration orchestration
- menu registration from manifest metadata
- plugin packaging
- release artifacts
- signature or provenance validation

# Future CI / Release / Versioning Hooks

The next phases should connect here:

- CI:
  validate every `plugin.json`, boot the plugin manager, and run plugin-specific tests in the main repository.
- Local lifecycle:
  the current `plugins:enable` and `plugins:disable` commands only persist local runtime state; later CI/release work should replace this with installable versioned plugin delivery.
- Compatibility checks:
  expand version constraint validation for plugin dependencies and public contract versions.
- Split repository publishing:
  connect each `plugins/<plugin-id>/` subtree to the downstream plugin repository defined by ADR-005.
- Versioning:
  enforce plugin version bumps when releasable plugin files change.
- Release:
  publish tags and artifacts from the split plugin repository, not directly from the main repository.

The current skeleton is intentionally small so those later automation steps can plug into a stable runtime model instead of replacing one.
