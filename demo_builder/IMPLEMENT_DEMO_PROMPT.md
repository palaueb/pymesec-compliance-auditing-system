# Demo Builder Prompt

Use this prompt when the task is to implement or refresh the demo edition of the application without introducing demo runtime behavior into the normal product branch.

## Mission

Build the demo edition through the `demo` branch and the `demo_builder/` patch workflow.

The normal application branch must stay free of demo-only runtime concepts unless the user explicitly changes that policy.
The demo must stay operational as product development continues; when product changes affect the demo surface, updating the exported patch pack is part of completing the work.

## Non-Negotiable Constraints

- Do not add demo-only runtime behavior to `main`.
- Keep demo logic isolated in the `demo` branch and export it as patch files under `demo_builder/patches/`.
- Prefer explicit, reviewable source changes over hidden deployment mutation.
- Generate one patch file per modified source file.
- Treat `demo_builder/state/` as local metadata only. Never rely on it as versioned source.
- If `main` drifted on files covered by the patch pack, stop and reconcile before exporting again.
- When a mainline feature touches demo-relevant screens, flows, or files already represented in `demo_builder/patches/`, treat demo refresh as mandatory, not optional.

## Available Tools

- `./demo_builder/demo-builder.sh init [base_branch] [demo_branch]`
- `./demo_builder/demo-builder.sh worktree [target_path]`
- `./demo_builder/demo-builder.sh export`
- `./demo_builder/demo-builder.sh drift`
- `./demo_builder/demo-builder.sh apply`

## Standard Procedure

1. Inspect the repository status and confirm the current base branch and HEAD.
2. Run `./demo_builder/demo-builder.sh init main demo` if the workflow is not initialized yet.
3. Create or reuse a dedicated demo worktree with `./demo_builder/demo-builder.sh worktree`.
4. Make all demo-only code changes inside the `demo` worktree, not in the main worktree.
5. Verify the demo branch locally as needed.
6. Return to the main worktree and run `./demo_builder/demo-builder.sh export`.
7. Run `./demo_builder/demo-builder.sh drift` to ensure the exported patch targets still match the base branch state.
8. Review the generated files under `demo_builder/patches/`.
9. Commit the builder updates on `main`.
10. Do not declare the feature complete until the demo impact has been checked and the patch pack has been refreshed when required.

## When Refreshing an Existing Demo Patch Pack

1. Sync `main`.
2. Run `./demo_builder/demo-builder.sh drift`.
3. If drift is reported, inspect the affected files on `main` before touching the demo branch.
4. Update the `demo` branch implementation.
5. Re-export the patch pack.

## Expected Deliverables

- Updated demo-specific implementation on the `demo` branch.
- Updated patch files in `demo_builder/patches/`.
- Updated documentation when the workflow changes.
- A concise summary explaining:
  - which files changed in the demo branch
  - which patches were regenerated
  - whether drift was detected and how it was handled

## Safety Checks

- Do not overwrite unrelated user changes.
- Do not reset or rewrite `main` history.
- Do not apply the patch pack onto a dirty checkout unless explicitly allowed.
- If the demo implementation starts requiring invasive core changes on `main`, stop and call that out explicitly.
