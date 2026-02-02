# Architecture Deep Dive: Sharing Metadata & Removing Issues

## Part 1: Sharing Release Metadata Across Machines

### The Problem
Local storage (`storage/app/release-metadata.json`) doesn't sync across team members' machines or CI/CD environments. You need **distributed** storage that travels with the git repository.

### Solution Comparison Matrix

| Approach | Distributed? | Complexity | Performance | Backwards Compat |
|----------|--------------|------------|-------------|------------------|
| **Git Notes** | ✅ (with push) | Medium | Fast | ✅ Good |
| **Commit Messages** | ✅ Native | Low | Fast | ✅ Excellent |
| **PR Description** | ✅ (via API) | High | Slow (API) | ⚠️ Limited |
| **Metadata File in Repo** | ✅ Native | Low | Fast | ✅ Excellent |
| **Git Config** | ❌ Local only | Medium | Fast | ❌ None |

### Recommended: Metadata File in Repository

**Why this is best for your architecture:**
1. **Git-native distribution** - Commits travel with push/pull automatically
2. **Laravel-familiar** - JSON file, just like your Forge cache pattern
3. **Human-readable** - Can inspect/debug easily
4. **Version controlled** - Changes tracked in git history
5. **CI/CD friendly** - No special setup needed

#### Implementation

```php
// File: app/Services/TracksReleaseMetadata.php
<?php

namespace App\Services;

use Illuminate\Support\Str;
use Illuminate\Support\Collection;

trait TracksReleaseMetadata
{
    private const METADATA_FILE = '.blacklabs/releases.json';
    
    /**
     * Store which issues are included in a release branch.
     * This creates/updates a JSON file in the repo that travels with git.
     */
    public function storeReleaseIssues(string $branchName, array $issues): void
    {
        $metadata = $this->loadMetadataFile();
        
        $normalizedBranch = str_replace('origin/', '', $branchName);
        
        $metadata[$normalizedBranch] = [
            'issues' => $issues,
            'version' => $this->extractVersionFromBranch($branchName),
            'created_at' => $metadata[$normalizedBranch]['created_at'] ?? now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'created_by' => $this->getGitUser(),
        ];
        
        $this->saveMetadataFile($metadata);
    }
    
    /**
     * Get issues for a release branch from the metadata file.
     * Falls back to parsing branch name for backwards compatibility.
     */
    public function getReleaseIssues(string $branchName): array
    {
        $metadata = $this->loadMetadataFile();
        $normalizedBranch = str_replace('origin/', '', $branchName);
        
        // Try exact match
        if (isset($metadata[$normalizedBranch]['issues'])) {
            return $metadata[$normalizedBranch]['issues'];
        }
        
        // Fallback: parse from branch name (legacy branches)
        $this->warn("No metadata found for {$normalizedBranch}, parsing from branch name");
        return $this->parseIssuesFromBranchName($branchName);
    }
    
    /**
     * Get all release metadata (useful for reporting).
     */
    public function getAllReleaseMetadata(): array
    {
        return $this->loadMetadataFile();
    }
    
    /**
     * Load the metadata file from the repository.
     */
    private function loadMetadataFile(): array
    {
        $path = getcwd() . '/' . self::METADATA_FILE;
        
        if (!file_exists($path)) {
            $this->initializeMetadataFile();
            return [];
        }
        
        $contents = file_get_contents($path);
        return json_decode($contents, true) ?? [];
    }
    
    /**
     * Save metadata file and stage it for commit.
     */
    private function saveMetadataFile(array $metadata): void
    {
        $path = getcwd() . '/' . self::METADATA_FILE;
        
        // Sort by branch name for consistent diffs
        ksort($metadata);
        
        $json = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($path, $json . "\n");
        
        // Stage the file automatically
        try {
            $this->runProcess('git add ' . self::METADATA_FILE);
        } catch (\Exception $e) {
            $this->warn("Could not stage metadata file: " . $e->getMessage());
        }
    }
    
    /**
     * Initialize metadata file structure.
     */
    private function initializeMetadataFile(): void
    {
        $dir = dirname(getcwd() . '/' . self::METADATA_FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $this->saveMetadataFile([]);
        
        // Add to .gitignore if it's not already tracked
        $gitignorePath = getcwd() . '/.gitignore';
        if (file_exists($gitignorePath)) {
            $gitignore = file_get_contents($gitignorePath);
            if (!str_contains($gitignore, self::METADATA_FILE)) {
                // We DON'T ignore it - we want it tracked!
                $this->info("Metadata file will be tracked in git");
            }
        }
    }
    
    /**
     * Extract version from branch name.
     */
    private function extractVersionFromBranch(string $branchName): string
    {
        // Match release/v1.2.3-4/... or release/v1.2.3-4
        if (preg_match('#release/(v?[\d\.]+(?:-\d+)?)#', $branchName, $matches)) {
            return $matches[1];
        }
        return 'unknown';
    }
    
    /**
     * Get current git user for audit trail.
     */
    private function getGitUser(): string
    {
        try {
            $name = trim($this->runProcess('git config user.name'));
            $email = trim($this->runProcess('git config user.email'));
            return "{$name} <{$email}>";
        } catch (\Exception $e) {
            return 'unknown';
        }
    }
    
    /**
     * Parse issues from legacy branch names (backwards compatibility).
     */
    private function parseIssuesFromBranchName(string $branchName): array
    {
        // Original logic: release/v1.2.3/123-456-789
        $issuesPart = Str::of($branchName)
            ->after('release/')
            ->after('/')
            ->toString();
        
        if (empty($issuesPart)) {
            return [];
        }
        
        return array_filter(explode('-', $issuesPart));
    }
}
```

#### Integration Changes

**1. Update CreateReleaseBranch.php:**
```php
// Add trait
use App\Services\TracksReleaseMetadata;

class CreateReleaseBranch extends Command
{
    use FindsIssueBranches;
    use RunsProcesses;
    use MergesBranches;
    use TracksReleaseMetadata;  // Add this
    
    public function handle(Git $git)
    {
        // ... existing code ...
        
        $version = $this->getNextVersionWithoutCommitting($level);
        
        // CHANGE: Simplify branch name (no issue list)
        $branchName = "release/{$version}";
        
        // ... merge branches ...
        
        // NEW: Store metadata
        $this->storeReleaseIssues($branchName, $issuesArray);
        
        // Officially apply the version
        $this->runProcess("npm version $version");
        
        // Metadata file is already staged from storeReleaseIssues()
        // The npm version commit will include it
        
        // ... push and create PR ...
    }
}
```

**2. Update AddOrRemoveIssueFromSite.php:**
```php
use App\Services\TracksReleaseMetadata;

class AddOrRemoveIssueFromSite extends Command
{
    use TracksReleaseMetadata;  // Add this
    
    public function handle()
    {
        // ... get site ...
        
        // CHANGE: Get issues from metadata instead of branch name
        $branchIssues = collect($this->getReleaseIssues($siteBranch));
        
        $branchVersion = $this->extractVersionFromBranch($siteBranch);
        $incrementedVersion = $this->incrementPrereleaseNumber($branchVersion);
        
        // Sync issues
        $issues = $isAdding
            ? $branchIssues->merge($issues)->unique()
            : $branchIssues->diff($issues);
        
        if ($isAdding) {
            // CHANGE: No issue list in branch name
            $branchName = "release/$incrementedVersion";
            
            $this->runProcess("git checkout {$siteBranch}");
            $this->runProcess("git pull origin {$siteBranch}");
            $this->runProcess("git checkout -b {$branchName}");
            
            // Merge new issues
            $addedIssues = $issues->diff($branchIssues);
            $branches = $this->findIssueBranches($addedIssues->toArray());
            $this->mergeBranches($branches);
            
            // NEW: Store updated metadata
            $this->storeReleaseIssues($branchName, $issues->toArray());
            
            $this->runProcess("npm version prerelease");
            $this->runProcess("git push origin {$branchName}");
            
            return 0;
        }
        
        // For removal: see Part 2 below
    }
}
```

**3. Update RemergeReleaseBranch.php:**
```php
use App\Services\TracksReleaseMetadata;

class RemergeReleaseBranch extends Command
{
    use TracksReleaseMetadata;  // Add this
    
    private function findReleaseBranches(array $issues): array
    {
        // CHANGE: Search in metadata file first
        $metadata = $this->getAllReleaseMetadata();
        $matchingBranches = [];
        
        foreach ($metadata as $branch => $data) {
            $branchIssues = $data['issues'] ?? [];
            foreach ($issues as $issue) {
                if (in_array($issue, $branchIssues)) {
                    $matchingBranches[] = $branch;
                    break; // Found a match, move to next branch
                }
            }
        }
        
        // Fallback: search by branch name pattern (legacy)
        if (empty($matchingBranches)) {
            foreach ($issues as $issue) {
                $thisIssuesBranches = $this->runProcess(
                    "git branch --list --remote 'origin/release/*/*{$issue}*'"
                );
                $matchingBranches = array_merge($matchingBranches, array_filter(
                    explode("\n", $thisIssuesBranches),
                    fn ($branch) => strlen($branch) > 0
                ));
            }
        }
        
        return array_unique($matchingBranches);
    }
}
```

#### Workflow Example

```bash
# Developer A creates release
blacklabs create-release-branch minor 123,456,789

# This creates:
# - Branch: release/v1.1.0-0
# - File: .blacklabs/releases.json with:
{
  "release/v1.1.0-0": {
    "issues": ["123", "456", "789"],
    "version": "v1.1.0-0",
    "created_at": "2025-11-11T10:00:00Z",
    "updated_at": "2025-11-11T10:00:00Z",
    "created_by": "Alice <alice@example.com>"
  }
}

# Developer B pulls the branch
git pull origin release/v1.1.0-0

# They can now run commands that need the issue list
blacklabs site add-issues testing.com 999
# ^ This reads from .blacklabs/releases.json automatically

# The metadata file updates:
{
  "release/v1.1.0-0": {
    "issues": ["123", "456", "789"],
    ...
  },
  "release/v1.1.0-1": {
    "issues": ["123", "456", "789", "999"],
    "version": "v1.1.0-1",
    "created_at": "2025-11-11T10:00:00Z",
    "updated_at": "2025-11-11T11:30:00Z",
    "created_by": "Bob <bob@example.com>"
  }
}
```

---

## Part 2: Removing Issues via Merge Reversion

### Understanding Merge Commits

When you run `git merge origin/123-feature-branch`, Git creates a **merge commit** with:
- **Two parents**: The release branch tip (parent 1) and the feature branch tip (parent 2)
- **Changes**: All the diff from the feature branch

```
release/v1.0.0-0
    |
    * --- Merge 123-feature
   /|\
  | | \
  | |  * (123-feature: changes to fileA, fileB)
  | |
  * --- Merge 456-bugfix
 /|\
| | \
| |  * (456-bugfix: changes to fileC)
|
* --- Initial commit
```

### The Revert Approach: Why It's Tricky

When you revert a merge commit with `git revert -m 1 <merge-sha>`, you're telling Git:
> "Keep parent 1 (the release branch), undo everything from parent 2 (the feature)"

#### Problems You Likely Encountered:

**Problem 1: Future Re-merges Don't Work**
```bash
# You revert issue 123
git revert -m 1 abc123  # The merge commit

# Later, you try to re-add issue 123
git merge origin/123-feature
# Result: "Already up to date" (Git thinks it's already merged!)
```

Git remembers the merge happened and won't re-merge unless the feature branch has new commits.

**Problem 2: Conflict Hell**
If the reverted changes overlap with other features, every subsequent merge conflicts with the revert.

**Problem 3: Confusing History**
```
* Revert "Merge 123-feature"
* Revert "Merge 456-bugfix"  
* Merge 456-bugfix
* Merge 123-feature
```
Becomes unreadable quickly.

### Better Alternative: Reconstruct Without the Issue

Instead of reverting, **create a clean branch without the unwanted issue**:

```php
// File: app/Services/ReconstructsReleaseBranches.php
<?php

namespace App\Services;

trait ReconstructsReleaseBranches
{
    /**
     * Create a new release branch without specific issues.
     * This is cleaner than reverting merge commits.
     */
    public function removeIssuesFromRelease(
        string $sourceBranch, 
        array $issuesToRemove,
        string $newVersion
    ): string {
        $this->info("Reconstructing release without issues: " . implode(', ', $issuesToRemove));
        
        // Get all issues from the source branch
        $allIssues = $this->getReleaseIssues($sourceBranch);
        $keepIssues = array_diff($allIssues, $issuesToRemove);
        
        if (empty($keepIssues)) {
            throw new \Exception("Cannot remove all issues from a release");
        }
        
        // Get the base version to find the right starting point
        $baseVersion = $this->getBaseVersionFromRelease($sourceBranch);
        
        // Create new branch from dev at the same point as original
        $newBranchName = "release/{$newVersion}";
        
        $this->info("Checking out dev...");
        $this->runProcess("git checkout dev");
        $this->runProcess("git pull origin dev");
        
        // Find the commit where the original release branched off
        $sourceBranchPoint = $this->runProcess(
            "git merge-base dev origin/{$sourceBranch}"
        );
        
        $this->info("Creating new branch from {$sourceBranchPoint}...");
        $this->runProcess("git checkout -b {$newBranchName} {$sourceBranchPoint}");
        
        // Merge only the issues we're keeping
        $this->info("Merging " . count($keepIssues) . " issues...");
        $branches = $this->findIssueBranches($keepIssues);
        $this->mergeBranches($branches);
        
        // Store metadata
        $this->storeReleaseIssues($newBranchName, $keepIssues);
        
        // Apply version
        $this->runProcess("npm version {$newVersion}");
        
        return $newBranchName;
    }
    
    /**
     * Get the base version (without prerelease number) from a release branch.
     */
    private function getBaseVersionFromRelease(string $branch): string
    {
        $version = $this->extractVersionFromBranch($branch);
        // v1.2.3-4 -> v1.2.3
        return preg_replace('/-\d+$/', '', $version);
    }
}
```

**Integration:**
```php
// In AddOrRemoveIssueFromSite.php
use App\Services\ReconstructsReleaseBranches;

public function handle()
{
    // ... get issues to remove ...
    
    if (!$isAdding) {
        $this->info('Removing issues from release...');
        
        $newBranch = $this->removeIssuesFromRelease(
            sourceBranch: $siteBranch,
            issuesToRemove: $branchIssues->diff($issues)->toArray(),
            newVersion: $incrementedVersion
        );
        
        $this->info("New branch created: {$newBranch}");
        
        // Update site and deploy
        $this->call('update-site-branch', [
            'site' => $site,
            'branch' => $newBranch,
        ]);
        
        return 0;
    }
}
```

### Comparison: Revert vs Reconstruct

| Aspect | Revert Merge Commits | Reconstruct Clean Branch |
|--------|---------------------|-------------------------|
| **History** | Messy, confusing | Clean, linear |
| **Re-merge** | Broken (Git remembers) | Works perfectly |
| **Conflicts** | High (reverts conflict with everything) | Normal (same as original) |
| **Speed** | Fast (one revert) | Slower (re-merge all) |
| **Git Rerere** | Doesn't help | Helps (same merge base) |
| **Audit Trail** | Clear what was removed | Less obvious |

### Hybrid Approach: Cherry-pick Resolutions

If you've already resolved complex conflicts in the original release, you can preserve those resolutions:

```php
public function removeIssuesPreservingResolutions(
    string $sourceBranch,
    array $issuesToRemove,
    string $newVersion
): string {
    // Start fresh
    $newBranch = $this->removeIssuesFromRelease(
        $sourceBranch, 
        $issuesToRemove, 
        $newVersion
    );
    
    // Cherry-pick any manual conflict resolution commits
    $resolutionCommits = $this->findManualResolutionCommits($sourceBranch);
    
    foreach ($resolutionCommits as $commit) {
        try {
            $this->runProcess("git cherry-pick {$commit}");
        } catch (\Exception $e) {
            $this->warn("Could not cherry-pick resolution {$commit}, may need manual merge");
            confirm("Continue?");
        }
    }
    
    return $newBranch;
}

private function findManualResolutionCommits(string $branch): array
{
    // Find commits that aren't merges and aren't the version bump
    $commits = $this->runProcess(
        "git log {$branch} --no-merges --format=%H --grep='version' --invert-grep"
    );
    
    return array_filter(explode("\n", $commits));
}
```

---

## Recommendation

### For Sharing Metadata:
**Use the metadata file in repo** (`.blacklabs/releases.json`)
- ✅ Distributed automatically with git
- ✅ Familiar pattern (JSON, Laravel-like)
- ✅ Version controlled
- ✅ Works in CI/CD without setup

### For Removing Issues:
**Use reconstruction** (not reversion)
- ✅ Clean history
- ✅ Git rerere actually helps
- ✅ No future re-merge problems
- ⚠️ Slower (but safer)

You can optimize with:
1. **Cache merge conflict resolutions** in git rerere
2. **Enable rerere globally**: `git config --global rerere.enabled true`
3. **Share rerere cache** via `.git/rr-cache` if needed

### Migration Path

1. **Phase 1**: Add metadata file tracking (this week)
   - Implement `TracksReleaseMetadata` trait
   - Update all commands to use it
   - Keep branch names simple: `release/v1.2.3-4`

2. **Phase 2**: Fix removal workflow (next sprint)
   - Implement `ReconstructsReleaseBranches` trait
   - Update `AddOrRemoveIssueFromSite` to reconstruct
   - Enable git rerere globally on all dev machines

3. **Phase 3**: Optimize (when needed)
   - Add cherry-pick resolution preservation
   - Benchmark and tune if slow
   - Consider GitHub API integration for advanced features
