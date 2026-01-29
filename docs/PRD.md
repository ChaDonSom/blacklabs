# PRD: Release Metadata Tracking (.blacklabs/releases.json)

## Summary
Add a simple, git-tracked metadata file that records which issue IDs are included in each release branch. This provides a single source of truth for release composition that travels with the repository and can be used by CLI commands and CI.

## Goals
- Ensure release issue lists are distributed across machines via git.
- Replace brittle branch-name parsing with metadata-first lookups.
- Provide an audit trail (who created/updated a release and when).
- Make automation safer (reconstruct/remove flows will use metadata).

## Scope
- Implement TracksReleaseMetadata trait for reading/writing `.blacklabs/releases.json`.
- Stage metadata file automatically when updated.
- Provide fallback: parse legacy branch names when metadata is missing.
- Add basic test (unit/feature) for read/write behavior.

## Out of scope (for now)
- Reconstruct release branch implementation (will remain separate).
- CI integration beyond ensuring the file is tracked.

## Success criteria
- PR merged with trait + docs + test.
- CLI commands use metadata for lookup (follow-up PR).

## UX
- After creating a release branch, metadata is written and committed as part of version bump.
- Commands read metadata first; if absent, parse branch name.

## Future enhancements
- Record per-issue merged commit SHAs
- Record source branch names and PR links
- Include deploy artifact IDs or CI run metadata

