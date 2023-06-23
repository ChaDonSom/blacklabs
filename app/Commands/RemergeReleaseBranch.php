<?php

namespace App\Commands;

use App\Services\FindsIssueBranches;
use App\Services\MergesBranches;
use App\Services\RunsProcesses;
use CzProject\GitPhp\Git;
use Illuminate\Support\Str;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class RemergeReleaseBranch extends Command
{
    use RunsProcesses;
    use FindsIssueBranches;
    use MergesBranches;

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
        $latestTag = Str::of($releaseBranch)->after('release/')->before('-'); // 0.15 || 0.15.0 || 0.15.0.4
        $tagParts = explode('.', $latestTag);
        $tagParts[2] = ($tagParts[2] ?? 0);
        $tagParts[3] = ($tagParts[3] ?? 0) + 1;
        $newTag = implode('.', $tagParts);
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
