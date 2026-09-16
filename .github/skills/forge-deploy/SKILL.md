---
name: forge-deploy
description: Use Forge token + org ID + deploy helpers for Black Labs CLI flows
---

Use this skill when working with Forge-related commands in this repo.

### Forge token & HTTP

- Forge API uses org-based endpoints (v1 deprecated); requires both token and org ID.
- Forge API token: `storage/app/forge-api-token.txt` (set via `php blacklabs app:store-forge-api-token <token>`).
- Forge organization ID: `storage/app/forge-org-id.txt` (set via `php blacklabs app:store-forge-org-id <orgId>`).
- Both stored in Storage (not env vars) for Phar compatibility.
- Forge HTTP helper: `App\Services\UsesForgeHttp::getForgeHttpRequest()` builds authenticated JSON client; `getForgeOrgId()` retrieves org ID; errors if either missing.

### Commands to offer

- Show site branches: `php blacklabs show-site-branches` (uses Forge to list console-tagged sites and branches).
- Update site branch and deploy: `php blacklabs update-site-branch-and-deploy` (prompts for site + branch, updates branch, triggers deploy).
- Add/remove issues from site: `php blacklabs site add-issues <domain> <issueList>` (updates release branch/tag for site and deploys).

### Setup prompts to offer

- "Store Forge API token and org ID"
- "Run: `php blacklabs app:store-forge-api-token <yourToken>` then `php blacklabs app:store-forge-org-id <yourOrgId>`"
