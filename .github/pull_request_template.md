## Summary

Describe what changed and why.

## Scope

- Module(s):
- API endpoints added/changed:
- OpenAPI operations added/changed:

## Mandatory Delivery Checklist

- [ ] Security and authorization checks are implemented (least privilege + object scope).
- [ ] Feature has API parity (or a documented approved exception in specs).
- [ ] All new/changed `/api/v1` routes include `_openapi` metadata.
- [ ] Module documentation updated under `docs/specs/` (including API behavior).
- [ ] In-app `HELP` content updated.
- [ ] OpenAPI artifacts regenerated and committed:
- [ ] `core/public/openapi.json`
- [ ] `core/public/openapi/v1.json`
- [ ] OpenAPI drift check passes: `php artisan openapi:publish --check`.
- [ ] Global validation passes:
- [ ] `composer lint`
- [ ] `composer test`
- [ ] Audit behavior verified for WEB/API changes.
- [ ] Demo impact evaluated and `demo_builder/patches/` refreshed if required.
- [ ] `docs/specs/project-todo.md` updated to real status.

## Validation Evidence

Paste the key command outputs:

```bash
composer lint
composer test
php artisan openapi:publish --check
```

## Risks / Follow-ups

List residual risks, deferred items, or follow-up tickets.
