---
name: forge-deploy
description: Use Forge token + deploy helpers for Black Labs CLI flows
---
Use this skill when working with Forge-related commands in this repo.

### Forge token & HTTP
- Forge API token is stored at `storage/app/forge-api-token.txt` (set via `php blacklabs app:store-forge-api-token <token>`).
- Forge HTTP helper: `App\Services\UsesForgeHttp::getForgeHttpRequest()` builds authenticated JSON client; errors if token missing.

### Commands to offer
- Show site branches: `php blacklabs show-site-branches` (uses Forge to list console-tagged sites and branches).
- Update site branch and deploy: `php blacklabs update-site-branch-and-deploy` (prompts for site + branch, updates branch, triggers deploy).
- Add/remove issues from site: `php blacklabs site add-issues <domain> <issueList>` (updates release branch/tag for site and deploys).

### Prompts to offer
- "List console sites and branches via Forge"
- "Update a site to branch X and deploy"
- "Add/remove issues 123,456 for site example.com and redeploy"
