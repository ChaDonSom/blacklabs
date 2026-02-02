## Build, Test, Lint
- Install deps: `composer install`
- Run full tests: `php blacklabs test` (Laravel Zero wrapper around Pest); direct: `./vendor/bin/pest`
- Run single test: `./vendor/bin/pest tests/Feature/CreateReleaseBranchTest.php --filter="works in a dummy git repo"`
- Lint/format: `./vendor/bin/pint`
- Build Phar: `php blacklabs app:build --build-version=<vX.Y.Z>` (writes `builds/blacklabs`); deployment flow in `app:deploy`

## Architecture
- Laravel Zero CLI packaged as a Phar via Box; `builds/blacklabs` is the binary.
- Commands live in `app/Commands/*`; services/traits in `app/Services/*`; providers in `app/Providers/*`.
- Git operations are centralized via `RunsProcesses` trait wrapping `Process::run` with exception on non-zero; many commands rely on git state.
- Release automation:
  - `create-release-branch` builds `release/<version>/<issues>` from `dev`, pulls issue branches (local or via GitHub), merges them, tags with `npm version`, pushes branch+tags, optionally creates PR via `gh`.
  - `deploy-to-production` guards with `forge-production` confirmation, merges chosen release into production, bumps version (npm), pushes tags, merges production into `dev`, deletes release branch.
  - `app:deploy` (hidden) builds Phar after running tests, tags, pushes; only allowed in upstream repo with clean master.
- Forge/branch helpers use `UsesForgeHttp` (token at `storage/app/forge-api-token.txt`) and `UsesGitHubCLI` (GraphQL via `gh`) to map issues↔branches.
- App boot adjusts logging/cache/filesystem paths for Phar vs local runs (see `AppServiceProvider`).

## Conventions & Patterns
- Branch naming: feature/issue branches start with issue number; release branches `release/<version>/<issue-list>`; production branch `forge-production`; primary dev branch `dev`.
- Versioning: npm-driven; `npm version <level|vX.Y.Z>` used in release/deploy flows; tags are `vX.Y.Z[-N]` and are pushed.
- Tests use Pest with `uses()->group('dummy-git-repo')` to spin up temp git repos; prefer grouping with `dummy-git-repo` when touching git-heavy flows.
- Merge helper `MergesBranches` handles merge conflicts by warning and pausing with prompt before continuing; respect this flow when extending merge logic.
- GitHub CLI required for PR creation and resolving missing issue branches; ensure `gh` auth in environments running automation.

## Notes for Copilot
- Prefer `RunsProcesses::runProcess` for shelling out; it throws on failure and returns trimmed output.
- When adding commands, register via `app/Commands` (auto-loaded) and keep side effects behind confirmations consistent with existing prompts.
- Packaging uses `box.json`; ensure new files required at runtime are included in Phar (directories list) if added outside existing paths.
- Skills: repository skills live in `.github/skills/`; load and manage skills each session (`/skills reload`, add/remove/update as needed). Provided skills: `laravel-zero` (core CLI workflows), `forge-deploy` (Forge flows), `box-phar` (packaging/Box).
