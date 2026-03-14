# Plugin Lifecycle and Settings UI v1

## Purpose

Define the operator-facing behavior of `Administration > Plugins`.

## Lifecycle State Model

Each discovered plugin exposes:

- `configured_enabled`
- `override_state`: `enabled`, `disabled`, or `null`
- `effective_enabled`
- runtime `booted`
- runtime `reason`
- `required_dependencies`
- enabled `dependent_plugins`

Derived labels in the shell:

- `enabled by config`
- `disabled by config`
- `enabled by override`
- `disabled by override`

## Allowed Actions

### Enable

Allowed when:

- the plugin exists
- every required dependency is effectively enabled

Result:

- writes an enable override when needed
- removes a previous disable override when the plugin is config-enabled
- becomes effective on the next request/bootstrap

### Disable

Allowed when:

- the plugin exists
- no effectively enabled plugin still requires it

Result:

- writes a disable override when needed
- removes a previous enable override when the plugin is config-disabled
- becomes effective on the next request/bootstrap

## Settings Entrypoints

Plugins may declare:

- `admin.settings_menu_id`

This points to a shell menu owned by the plugin. The core `Plugins` screen:

- links to it when the current context can see that menu
- otherwise shows that additional workspace context is required

This keeps settings ownership inside the plugin while making the route discoverable from administration.

## Runtime Safeguards

The plugin manager must refuse to boot an enabled plugin when:

- a required dependency is not effectively enabled
- a required dependency failed to become active during bootstrap

In those cases, status data must expose the dependency failure through `reason` and `missing_dependencies`.

## Current v1 Notes

- lifecycle transport from the shell uses AJAX-backed form posts
- lifecycle actions redirect back into the shell with controlled success or error feedback
- install, uninstall, and upgrade remain out of scope
- plugin settings remain plugin-owned screens, not generic core forms
