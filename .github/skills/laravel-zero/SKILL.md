---
name: laravel-zero-cli
description: Core workflows for the Black Labs Laravel Zero CLI (build, test, lint, release helpers)
---
Use this skill when working in this repo or similar Laravel Zero CLI projects.

### Commands
- Install: `composer install`
- Test (full): `php blacklabs test` or `./vendor/bin/pest`
- Test (single): `./vendor/bin/pest tests/Feature/CreateReleaseBranchTest.php --filter="works in a dummy git repo"`
- Lint/format: `./vendor/bin/pint`
- Build Phar: `php blacklabs app:build --build-version=<vX.Y.Z>`

### Release/Deploy flows
- Create release branch: `php blacklabs create-release-branch <level|vX.Y.Z[-N]> <issue1,issue2>` (merges issue branches, tags via npm, pushes branch+tags, may call `gh pr create`).
- Deploy to production: `php blacklabs deploy-to-production [release/...]` (confirms `forge-production`, merges to prod, bumps npm version, merges prod -> dev, deletes release branch).
- Packagist deploy: `php blacklabs app:deploy` (must be clean master on upstream repo; runs tests, builds Phar, tags/pushes).

### Conventions
- Branches: issue branches start with issue number; release branches `release/<version>/<issues>`; production branch `forge-production`; primary dev branch `dev`.
- Versioning: npm-based tags `vX.Y.Z[-N]`; tags pushed.
- Use `App\Services\RunsProcesses` for shelling out; merge conflicts handled via `MergesBranches` (pauses for manual resolution).

### Prompts to offer
- "Run lint + tests before packaging": run pint then pest, then `app:build`.
- "Create release branch for issues 123,456 as preminor": call `create-release-branch preminor 123,456`.
- "Deploy release branch to prod and sync dev": call `deploy-to-production <branch>`.
- "Reload and manage skills each session": `/skills reload` then add/remove as needed.
