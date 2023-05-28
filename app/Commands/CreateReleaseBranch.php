<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;
use CzProject\GitPhp\Git;
use Illuminate\Support\Facades\Log;

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
    public function handle(Git $git) {
        $version = $this->argument('version');
        $issues = $this->argument('issues');

        $this->info("Creating release branch for version {$version}.");

        $this->info("Checking out dev branch.");
        $repo = $git->open(getcwd());
        $repo->checkout('dev');

        $this->info("Pulling latest dev branch.");
        $repo->pull();

        $this->info("Creating release branch.");
        $issuesFormattedForBranch = str_replace(',', '-', $issues);
        $branchName = "release/{$version}-{$issuesFormattedForBranch}";
        try {
            $this->runProcess("git checkout -b {$branchName}");
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'already exists')) {
                $this->error("Branch {$branchName} already exists. Please delete it and try again.");
            }
            return;
        }

        $this->info("Pulling issue branches into release branch.");
        $issuesArray = explode(',', $issues);
        $issueBranches = collect($issuesArray)->map(function ($issue) {
            $this->info("Merging issue {$issue} into release branch.");
            // Get the branch name for the issue from the issue number, using git
            try {
                $issueBranchNames = $this->runProcess("git branch -r | grep -v HEAD | sed -e 's/^[[:space:]]*//' | sed -e 's/origin\///' | grep {$issue}");
            } catch (\Exception $e) {
                $this->warn("No branch found for issue {$issue}. Skipping.");
                return null;
            }
            $issueBranchNamesArray = collect(explode("\n", $issueBranchNames))
                ->filter(fn ($branchName) => !preg_match('/^release/', $branchName))
                ->filter(fn ($branchName) => preg_match('/^' . $issue . '/', $branchName))
                ->filter() // Filter out empty strings
                ->toArray();
            if (count($issueBranchNamesArray) === 0) {
                $this->warn("No branch found for issue {$issue}. Skipping.");
                return null;
            }
            $issueBranchName = $issueBranchNamesArray[0];
            if (count($issueBranchNamesArray) > 1) {
                $issueBranchName = $this->choice(
                    "More than one branch found for issue {$issue}. Which one would you like to use?",
                    $issueBranchNamesArray
                );
            }
            return $issueBranchName;
        })->filter()->toArray();

        foreach ($issueBranches as $issueBranchName) {
            try {
                $this->runProcess("git merge origin/{$issueBranchName}");
            } catch (\Exception $e) {
                // If the merge results in merge conflicts, pause the merge and continue when ready.
                if (str_contains($e->getMessage(), 'merge failed')) {
                    $issue = explode('-', $issueBranchName)[0];
                    $this->warn("Merge conflict detected with issue {$issue}."
                        . " Please resolve manually, then continue when you've committed the merge.");
                    $this->confirm("Ready to continue?");
                } else {
                    Log::error($e->getMessage());
                    return $this->error($e->getMessage());
                }
            }
        }

        $this->info("Pushing release branch to origin.");
        $this->runProcess("git push origin $branchName");

        $this->info("Creating release PR.");
        $prBody = "- #" . implode("\n- #", $issuesArray) . "\n";
        // We don't run this in testing mode, because I don't feel like figuring out how to mock the gh command for now.
        if (!app()->runningUnitTests()) {
            try {
                $this->runProcess(<<<bash
                    gh pr create \
                        --title 'Release {$version}' \
                        --body '$prBody' \
                        --base dev \
                        --head {$branchName} \
                        --assignee @me
                bash);
            } catch (\Exception $e) {
                $this->warn("Failed to create PR. Please create it manually.");
            }
        }

        // Create and push a tag for the release
        $this->info("Creating release tag.");
        $this->runProcess("git tag -a v{$version} -m 'Release {$version}'");
        $this->runProcess("git push origin v{$version}");

        $this->info("Done.");
        $this->info("Branch: {$branchName}");
    }

    public function runProcess($command) {
        $result = Process::run($command);
        if (!$result->successful()) {
            throw new \Exception($result->errorOutput() ?: $result->output());
        }
        return $result->output();
    }
}
