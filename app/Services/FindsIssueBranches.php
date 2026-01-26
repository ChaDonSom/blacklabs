<?php

namespace App\Services;

trait FindsIssueBranches
{
    use RunsProcesses;
    use UsesGitHubCLI;

    /**
     * Find the branch names for the given issues.
     *
     * @param  array  $issuesArray  An array of issue numbers
     */
    public function findIssueBranches(array $issuesArray): array
    {
        return collect($issuesArray)->map(function ($issue) {
            $this->info("Finding branch for issue {$issue}...");
            // Get the branch name for the issue from the issue number, using git
            try {
                $issueBranchNames = $this->runProcess("git branch -r | grep -v HEAD | sed -e 's/^[[:space:]]*//' | sed -e 's/origin\///' | grep {$issue}");
            } catch (\Exception $e) {
                // For some reason, we don't get any output (maybe because it's piped?)
                $issueBranchNames = '';
            }
            $issueBranchNamesArray = collect(explode("\n", $issueBranchNames))
                ->filter(fn ($branchName) => ! preg_match('/^release/', $branchName))
                ->filter(fn ($branchName) => preg_match('/^'.$issue.'/', $branchName))
                ->filter() // Filter out empty strings
                ->toArray();
            if (count($issueBranchNamesArray) === 0) {
                // Try to find the branch using GitHub CLI
                $this->info("Checking GitHub for a branch linked to issue {$issue}...");
                $githubBranch = $this->getBranchForIssue($issue);
                if ($githubBranch) {
                    $this->info("Found branch {$githubBranch} linked to issue {$issue} on GitHub.");

                    return $githubBranch;
                }
                $this->warn("No branch found for issue {$issue}. Please choose one, or skip this issue for now.");
                // Let the user choose a branch to merge in
                $issueBranchNames = $this->runProcess("git branch -r | grep -v HEAD | sed -e 's/^[[:space:]]*//' | sed -e 's/origin\///'");
                $issueBranchesArray = collect(explode("\n", $issueBranchNames))
                    ->filter(fn ($branchName) => ! preg_match('/^release/', $branchName))
                    ->filter() // Filter out empty strings
                    ->push('Skip')
                    ->toArray();
                $issueBranchName = $this->choice("Choose a branch for issue {$issue}", $issueBranchesArray, 'Skip');
                if ($issueBranchName === 'Skip') {
                    return null;
                }
            }
            $issueBranchName ??= $issueBranchNamesArray[0];
            if (count($issueBranchNamesArray) > 1) {
                $issueBranchName = $this->choice(
                    "More than one branch found for issue {$issue}. Which one would you like to use?",
                    $issueBranchNamesArray
                );
            }

            return $issueBranchName;
        })->filter()->toArray();
    }
}
