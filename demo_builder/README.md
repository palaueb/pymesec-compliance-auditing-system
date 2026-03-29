# Demo Builder

`demo_builder/` keeps the demo workflow outside the application runtime.

The intended model is:
- `main` keeps the real product code and the builder tooling.
- `demo` is a local branch where demo-only code changes live.
- `demo_builder/patches/` stores one generated patch per changed file.
- `demo_builder/IMPLEMENT_DEMO_PROMPT.md` is the working prompt for future demo refresh tasks.

The demo is a maintained deliverable.
If product work changes demo-relevant behavior or touches files already covered by the patch pack, the demo branch and exported patches must be refreshed as part of that work.

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
- Product work is not finished if the demo surface was affected and the required patch refresh was skipped.

## Walkthrough: Refresh And Republish The Demo

This is the operational flow when:
- `main` has changed
- the published demo needs those changes
- the server checkout is already dirty because the demo patch pack was applied before

### 1. Understand which script is used where

- Use `./demo_builder/demo-builder.sh` in the source repository where you prepare or refresh the demo patch pack.
- Use `./demo_builder/deploy-demo.sh` only on the server checkout where the demo is published.

Do not treat the published demo checkout as a normal development checkout.
Once the demo patch pack is applied there, `git status` will show modified tracked files by design.

### 2. In the source repository, update `main`

Start from the repository where you maintain the product code and the patch files:

```bash
git checkout main
git pull --ff-only origin main
```

### 3. Check whether the existing demo patch pack drifted

Run:

```bash
./demo_builder/demo-builder.sh drift
```

Outcomes:
- If it says `no drift detected`, the current patch pack is still aligned with `main`.
- If it prints `DRIFT ...`, at least one file changed on `main` since the last export. The demo patch pack must be refreshed before deploying again.
- If it says export metadata is missing, the workflow must be initialized again in the source repository, not on the published server checkout.

### 4. Refresh the demo patch pack if drift exists

If drift exists, rebuild the demo variant against the current `main`, then export the patch files again.

If you are fine keeping a local `demo` branch:

```bash
./demo_builder/demo-builder.sh init main demo
./demo_builder/demo-builder.sh worktree
```

Then make or reconcile the demo-only changes inside the demo worktree, not on `main`.

When the demo-only changes are correct, export the patch pack:

```bash
./demo_builder/demo-builder.sh export
./demo_builder/demo-builder.sh drift
```

`drift` should come back clean after the export.

If you do not want a persistent `demo` branch in your normal workspace, do the same refresh in a temporary clone or temporary worktree and only bring back the regenerated files under `demo_builder/patches/`.

### 5. Commit and push the refreshed patch files

Once the patch pack is correct:

```bash
git add demo_builder/patches
git commit -m "demo: refresh patch pack"
git push
```

Only the patch files need to be pushed for deployment. The local metadata under `demo_builder/state/` is intentionally not part of deployment.

### 6. On the published demo server, reset the checkout back to clean `main`

The published demo checkout is expected to be dirty after a previous deploy, because the patch pack modifies tracked files.
That means you cannot safely do a plain `git pull` there.

Reset it first:

```bash
git fetch origin
git reset --hard origin/main
git clean -fd
```

Notes:
- This removes the previously applied demo patch changes from the checkout.
- Do not use `git clean -fdx` unless you explicitly want to remove ignored files too.
- If you have a separate persistent runtime area outside git, preserve it according to your hosting setup.

### 7. Pull the latest code on the server

After the reset:

```bash
git pull --ff-only origin main
```

At this point the server checkout should be a clean copy of `main` plus the newly pushed patch files.

### 8. Reapply the demo and rebuild it on the server

Run the demo deploy script from the server checkout:

```bash
./demo_builder/deploy-demo.sh --app-url https://demo.pimesec.com
```

If the SSH-visible root differs from the web-server-visible root, pass one of:

```bash
./demo_builder/deploy-demo.sh --web-root /home/pimesec.com/demo.pimesec.com
./demo_builder/deploy-demo.sh --web-prefix /home/pimesec.com
```

What this script does:
- applies the patch pack under `demo_builder/patches/`
- creates or updates `core/.env`
- configures demo environment values
- prepares writable storage directories
- runs `composer install`
- runs `artisan key:generate --force`
- runs `artisan optimize:clear`
- runs `artisan demo:build-template`

### 9. Verify the result

Check:
- `git status` on the server will normally be dirty again after deploy
- the application opens at the demo URL
- login works with `admin / demo`
- the document root points to `core/public`

### 10. Practical rule of thumb

Use this mental model:
- `demo-builder.sh` = maintain or refresh the patch pack
- `deploy-demo.sh` = apply that patch pack onto a server checkout

If the server checkout is dirty, that is normal after deployment.
If you want to update it, reset it back to clean `main`, pull, and run `deploy-demo.sh` again.
