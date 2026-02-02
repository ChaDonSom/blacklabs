---
name: box-phar
description: Build and package the Laravel Zero CLI Phar with Box
---
Use this skill for packaging tasks in this repo.

### Build artifacts
- Phar binary output: `builds/blacklabs`
- Box config: `box.json` includes app/bootstrap/config/vendor directories and composer.json; compression GZ.

### Commands
- Build Phar: `php blacklabs app:build --build-version=<vX.Y.Z>` (used in deploy flow).
- Deploy flow runs: tests -> build Phar -> commit -> tag/push (`php blacklabs app:deploy`).

### Prompts to offer
- "Build the Phar at version vX.Y.Z"
- "Run tests then build Phar for release"
- "Confirm Box includes new runtime files if added outside app/bootstrap/config/vendor"
