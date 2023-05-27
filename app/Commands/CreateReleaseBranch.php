<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

class CreateReleaseBranch extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'devops:create-release-branch
                            {version : The version number for the release (required)}
                            {issues : The issues to pull into the release branch (required, comma-separated)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a release branch from dev, pulling in the listed issues and creating a PR.';

    /**
     * Execute the console command.
     */
    public function handle() {
        $version = $this->argument('version');
        $issues = $this->argument('issues');

        $this->info("Creating release branch for version {$version}.");

        $this->info("Checking out dev branch.");
        $this->runProcess('git checkout dev');

        $this->info("Pulling latest dev branch.");
        $this->runProcess('git pull');

        $this->info("Creating release branch.");
        $issuesFormattedForBranch = str_replace(',', '-', $issues);
        $branchName = "release/{$version}-{$issuesFormattedForBranch}";
        try {
            $result = $this->runProcess("git checkout -b {$branchName}");
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'already exists')) {
                $this->error("Branch {$branchName} already exists. Please delete it and try again.");
            }
            return;
        }

        $this->info("Pulling issue branches into release branch.");
        $issuesArray = explode(',', $issues);
        foreach ($issuesArray as $issue) {
            $this->info("Merging issue {$issue} into release branch.");
            // Get the branch name for the issue from the issue number, using git
            $issueBranchNames = $this->runProcess("git branch -r | grep -v HEAD | sed -e 's/^[[:space:]]*//' | sed -e 's/origin\///' | grep {$issue}");
            $issueBranchNamesArray = collect(explode("\n", $issueBranchNames))
                ->filter(fn ($branchName) => !preg_match('/^release/', $branchName))
                ->filter() // Filter out empty strings
                ->toArray();
            if (count($issueBranchNamesArray) === 0) {
                $this->warn("No branch found for issue {$issue}. Skipping.");
                continue;
            }
            $issueBranchName = $issueBranchNamesArray[0];
            if (count($issueBranchNamesArray) > 1) {
                $issueBranchName = $this->choice(
                    "More than one branch found for issue {$issue}. Which one would you like to use?",
                    $issueBranchNamesArray
                );
            }
            try {
                $result = $this->runProcess("git merge origin/{$issueBranchName}");
            } catch (\Exception $e) {
                // If the merge results in merge conflicts, abort the merge and continue
                if (str_contains($e->getMessage(), 'merge failed')) {
                    $this->warn("Merge conflict detected for issue {$issue}. Aborting merge. Please resolve manually after the script completes.");
                    $this->runProcess("git merge --abort");
                    continue;
                } else {
                    return $this->error($e->getMessage());
                    // throw $e;
                }
            }
        }

        $this->info("Pushing release branch to origin.");
        $this->runProcess("git push origin $branchName");

        $this->info("Creating release PR.");
        $prBody = "- #" . implode("\n- #", $issuesArray) . "\n";
        $this->runProcess("gh pr create --title 'Release {$version}' --body '$prBody' --base dev --head {$branchName} --assignee @me");

        // Create and push a tag for the release
        $this->info("Creating release tag.");
        $this->runProcess("git tag -a v{$version} -m 'Release {$version}'");
        $this->runProcess("git push origin v{$version}");

        $this->info("Done.");
        $this->info("Branch: {$branchName}");
    }

    public function runProcess($command) {
        $result = Process::run($command);
        if (!$result->successful()) throw new \Exception($result->errorOutput());
        return $result->output();
    }
}
