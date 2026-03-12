# Title

Plugin UI Contract v1

# Status

Draft

# Context

The core now provides:

- a shell layout
- a menu registry
- a screen registry
- core-governed themes

The platform therefore needs a minimal UI contract for plugins so they can contribute real screens without taking control of the shell itself.

# Specification

## 1. Purpose

This contract defines the minimum UI surface a plugin may contribute to the core shell in v1.

## 2. Core-Owned Concerns

The core owns:

- the shell layout
- theme tokens
- menu rendering
- toolbar placement
- tenancy framing
- shared visual behavior

Plugins must not redefine those concerns.

## 3. Plugin-Contributed Concerns

A plugin may contribute a screen definition bound to a menu item.

In v1 a screen definition includes:

- owning plugin
- target menu identifier
- title translation key
- subtitle translation key
- Blade view path or equivalent approved render target
- optional data resolver
- optional toolbar action resolver

## 4. Rendering Model

The shell selects the active menu item and asks the screen registry whether a screen definition exists for that menu.

If a screen exists:

- the shell renders the screen inside the content area
- the shell renders toolbar actions in the shared toolbar area

If no screen exists:

- the shell may render a neutral fallback or preview state

## 5. Safety Rules

- plugins may render content, not shell chrome
- plugins may propose toolbar actions, not toolbar layout
- plugins may not inject arbitrary theme overrides into the shell
- plugin views must respect the shared theme token model

## 6. Workflow and Domain Integration

Plugins may use core services such as:

- permissions
- workflow engine
- menus
- tenancy context
- audit trail

UI contributions must also respect the permission runtime:

- hidden menu items are not enough
- screen actions that mutate state must be suppressed or disabled when permission is missing
- direct plugin routes may be protected with core authorization middleware

This allows domain screens to remain thin UI layers over stable core contracts.

# Consequences

- Plugins can contribute real HTML screens early.
- The shell stays consistent while still becoming useful.
- Future widget, form, and page contracts can evolve from one minimal runtime model.
