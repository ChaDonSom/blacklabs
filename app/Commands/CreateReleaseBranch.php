<?php

namespace App\Commands;

use App\Services\FindsIssueBranches;
use App\Services\MergesBranches;
use App\Services\RunsProcesses;
use App\Services\Tags;
use LaravelZero\Framework\Commands\Command;
use CzProject\GitPhp\Git;
use Illuminate\Support\Facades\Log;

class CreateReleaseBranch extends Command {
    use FindsIssueBranches;
    use RunsProcesses;
    use MergesBranches;
    use Tags;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create-release-branch
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
        $version = $this->getTagWithV($this->argument('version'));
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
        $issueBranches = $this->findIssueBranches($issuesArray);

        $this->mergeBranches($issueBranches);

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
        // Remove any 'v' from the version number
        $version = str_replace('v', '', $version);
        $this->runProcess("git tag -a v{$version} -m 'Release {$version}'");
        $this->runProcess("git push origin v{$version}");

        $this->info("Done.");
        $this->info("Branch: {$branchName}");
    }
}
