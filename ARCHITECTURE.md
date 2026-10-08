# Architecture

- `bootstrap/app.php` creates the Laravel Zero application. Commands in `app/Commands` own CLI workflows and prompts.
- Commands use traits in `app/Services` for shared capabilities. Forge commands use `UsesForgeHttp` for authenticated HTTP and `GetsConsoleSites` for site discovery and short-lived caching.
- Forge state changes flow from a command to the organization-scoped Forge API. Feature tests live in `tests/Feature` and use Laravel's HTTP fakes for API behavior.
- `builds/blacklabs` is a packaged binary generated from the Laravel Zero application; `vendor/` contains dependencies and is not application source.
