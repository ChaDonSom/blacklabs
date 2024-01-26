<?php

namespace App\Commands;

use App\Services\FindsIssueBranches;
use App\Services\MergesBranches;
use App\Services\RunsProcesses;
use Illuminate\Support\Facades\Log;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\search;

class RemergeReleaseBranch extends Command
{
    use RunsProcesses;
    use FindsIssueBranches;
    use MergesBranches;

    protected $signature = 'remerge-release-branch
                            {issues? : The issue branches to merge into the release branch (comma-separated)}
                            {release-branch? : The release branch to merge new changes into}
                            ';

    public function handle()
    {
        $releaseBranch = $this->argument('release-branch');
        $issues = $this->argument('issues');

        if (!$issues) {
            // Try get the issue from the current branch
            $this->info("No issues given, trying to get the issue from the current branch...");
            $currentBranch = $this->runProcess('git rev-parse --abbrev-ref HEAD');
            if ($this->isIssueBranch($currentBranch)) $issues = $this->getIssueNumberFromIssueBranch($currentBranch);
        }

        if (!$issues) {
            $this->error("No issues given and no issue branch checked out.");
            return;
        }

        if (!$releaseBranch) {
            // Get the release branches that include the given issues in their names
            $this->info("No release branch given, trying to find the release branch from the issues...");
            $releaseBranches = $this->findReleaseBranches(explode(',', $issues));
            print_r($releaseBranches);
            $this->info("Found release branches: " . implode(', ', $releaseBranches));
            if (count($releaseBranches) === 0) {
                $this->error("No release branches found for the given issues.");
                return;
            } elseif (count($releaseBranches) === 1) {
                $releaseBranch = $releaseBranches[0];
            } elseif (count($releaseBranches) > 1) {
                $releaseBranch = search(
                    label: $message ?? 'What branch would you like to merge into?',
                    options: fn (string $value) => strlen($value) > 0
                        ? collect($releaseBranches)->filter(fn ($branch) => str_contains($branch, $value))->values()->toArray()
                        : collect($releaseBranches)->toArray(),
                    scroll: 10,
                );
            }
        }

        // Find the issue branches from the issue numbers
        $issueBranches = $this->findIssueBranches(explode(',', $issues));

        // Merge the issue branches into the release branch
        $this->runProcess('git checkout ' . $releaseBranch);
        $this->mergeBranches($issueBranches);

        // Increment the tag's deploy number (prerelease number)
        // Increment the tag using npm version prerelease, then get the new tag
        $this->runProcess("npm version prerelease");

        // Push the branch and the tags
        $this->info("Pushing the branch and the tag...");
        $this->runProcess("git push origin {$releaseBranch} --follow-tags");

        $this->info("Done!");
    }

    /**
     * Determine whether the given branch is an issue branch. Issue branches are named {issue number}-{issue title slug}.
     */
    private function isIssueBranch(string $branchName): bool
    {
        return preg_match('/^\d+-/', $branchName);
    }

    /**
     * Get the issue number from the given issue branch name.
     */
    private function getIssueNumberFromIssueBranch(string $branchName): string
    {
        return explode('-', $branchName)[0];
    }

    /**
     * Get the release branches that include the given issues in their names. Release branches are formatted like
     * release/{version}/{issue numbers, kebab-case}.
     */
    private function findReleaseBranches(array $issues): array
    {
        $releaseBranches = [];
        foreach ($issues as $issue) {
            // Find all release branches that have this issue's number anywhere in their name
            $thisIssuesBranches = $this->runProcess(
                "git branch --list --remote 'origin/release/*/*{$issue}*'"
            );
            print_r('thisIssuesBranches: ');
            print_r($thisIssuesBranches);
            $releaseBranches = array_merge($releaseBranches, array_filter(
                explode("\n", $thisIssuesBranches),
                fn ($branch) => strlen($branch) > 0
            ));
        }
        return $releaseBranches;
    }
}
