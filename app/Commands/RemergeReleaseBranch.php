<?php

namespace App\Commands;

use App\Services\FindsIssueBranches;
use App\Services\MergesBranches;
use App\Services\RunsProcesses;
use App\Services\Tags;
use CzProject\GitPhp\Git;
use Illuminate\Support\Str;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class RemergeReleaseBranch extends Command
{
    use RunsProcesses;
    use FindsIssueBranches;
    use MergesBranches;
    use Tags;

    protected $signature = 'merge-and-increment-tag
                            {release-branch : The release branch to merge new changes into (required)}
                            {issues : The issue branches to merge into the release branch (required, comma-separated)}';

    public function handle() {
        $releaseBranch = $this->argument('release-branch');
        $issues = $this->argument('issues');

        // Find the issue branches from the issue numbers
        $issueBranches = $this->findIssueBranches(explode(',', $issues));

        // Merge the issue branches into the release branch
        $this->runProcess('git checkout ' . $releaseBranch);
        $this->mergeBranches($issueBranches);

        // Increment the tag's deploy number
        // Get the tag from the release branch name
        $this->info("Incrementing the tag...");
        $latestTag = $this->getTagFromBranch($releaseBranch); // v0.15 || v0.15.0 || v0.15.0.4

        $latestTag = $this->getGitTagFromBranchTag($latestTag);

        $newTag = $this->incrementTag($latestTag);
        $this->info("New tag: {$newTag}");

        // Create the new tag
        $this->runProcess("git tag {$newTag}");

        // Push the branch and the tags
        $this->info("Pushing the branch and the tag...");
        $this->runProcess("git push origin {$releaseBranch}");
        $this->runProcess("git push origin {$newTag}");

        $this->info("Done!");
    }
}
