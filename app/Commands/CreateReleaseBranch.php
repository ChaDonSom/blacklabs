<?php

namespace App\Commands;

use App\Services\FindsIssueBranches;
use App\Services\MergesBranches;
use App\Services\RunsProcesses;
use LaravelZero\Framework\Commands\Command;
use CzProject\GitPhp\Git;
use Exception;
use Illuminate\Support\Facades\Log;

class CreateReleaseBranch extends Command {
    use FindsIssueBranches;
    use RunsProcesses;
    use MergesBranches;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create-release-branch
                            {level : The level of the release (major, minor, patch, prerelease) (required)}
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
        $level = $this->argument('level');
        $issues = $this->argument('issues');

        $this->info("Checking out dev branch.");
        $repo = $git->open(getcwd());
        $repo->checkout('dev');

        $this->info("Pulling latest dev branch.");
        $repo->pull();

        $version = $this->getNextVersionWithoutCommitting($level); // This is because we don't want to commit the
        // version change until we've merged the issue branches in
        Log::debug("Version: {$version}");
        
        $this->info("Creating release branch for version {$version}.");

        $issuesFormattedForBranch = str_replace(',', '-', $issues);
        $branchName = "release/{$version}/{$issuesFormattedForBranch}";
        try {
            $this->runProcess("git checkout -b {$branchName}");
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'already exists')) {
                $shouldDeleteIt = $this->confirm("Branch {$branchName} already exists. Should we delete it?", true);
                if ($shouldDeleteIt) {
                    $this->runProcess("git branch -D {$branchName}");
                    $this->runProcess("git checkout -b {$branchName}");
                } else {
                    $this->error("Branch {$branchName} already exists. Please delete it or choose a different version.");
                    return;
                }
            }
        }

        $this->info("Pulling issue branches into release branch.");
        $issuesArray = explode(',', $issues);
        Log::debug("Issues array: " . print_r($issuesArray, true));
        $issueBranches = $this->findIssueBranches($issuesArray);

        $this->mergeBranches($issueBranches);

        // Officially apply the version
        $this->runProcess("npm version $level");

        $this->info("Pushing release branch to origin.");
        $this->runProcess("git push origin $branchName");
        $this->runProcess("git push origin --tags");

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

        $this->info("Done.");
        $this->info("Branch: {$branchName}");
    }

    /**
     * Get the next version number. This will run npm version, then throw out the file changes and tag.
     * @param string $level The level of the release (major, minor, patch, prerelease)
     * @return string The next version number
     * @throws Exception If something goes wrong with the commands
     */
    public function getNextVersionWithoutCommitting(string $level): string {
        // Get the next version
        $version = $this->runProcess("npm version $level --no-git-tag-version");

        // Toss out the changes to package.json
        $this->runProcess("git checkout package.json");
        $this->runProcess("git checkout package-lock.json");
        // Also toss out the tag
        if ($this->runProcess("git tag -l {$version}")) $this->runProcess("git tag -d {$version}");

        return $version;
    }
}
