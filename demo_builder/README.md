# Demo Builder

`demo_builder/` keeps the demo workflow outside the application runtime.

The intended model is:
- `main` keeps the real product code and the builder tooling.
- `demo` is a local branch where demo-only code changes live.
- `demo_builder/patches/` stores one generated patch per changed file.
- `demo_builder/IMPLEMENT_DEMO_PROMPT.md` is the working prompt for future demo refresh tasks.

## Quick Start

Initialize the workflow once:

```bash
./demo_builder/demo-builder.sh init main demo
```

Create a dedicated worktree for the `demo` branch:

```bash
./demo_builder/demo-builder.sh worktree
```

Build or refresh the patch pack after changing the `demo` branch:

```bash
./demo_builder/demo-builder.sh export
```

Check whether any file touched by the patch pack changed on `main` since the last export:

```bash
./demo_builder/demo-builder.sh drift
```

Apply the patch pack onto a clean checkout pinned to the exported base commit:

```bash
./demo_builder/demo-builder.sh apply
```

Deploy the demo edition onto an uploaded checkout:

```bash
./demo_builder/deploy-demo.sh --app-url https://demo.pymesec.com
```

If SSH is jailed and the web server sees a different absolute root, pass one of:

```bash
./demo_builder/deploy-demo.sh --web-root /home/pymesec.com/demo.pymesec.com
./demo_builder/deploy-demo.sh --web-prefix /home/pymesec.com
```

## Notes

- `init` only creates the `demo` branch and records workflow metadata. It does not switch branches.
- `deploy-demo.sh` applies the exported patch pack idempotently and prepares the server checkout as a runnable demo installation.
- `export` regenerates the full patch set from `main...demo` and writes one patch per file.
- `drift` is strict by design. If it reports drift, the patch pack should be reviewed and regenerated.
- `apply` requires the checkout to be clean and at the exact base commit captured during export.
- Metadata is stored in `demo_builder/state/`.
