<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class RemergeReleaseBranch extends Command
{
    protected $signature = 'merge-and-increment-tag {release-branch : The release branch to merge new changes into} {issues* : The issue branches to merge into the release branch}';

    public function handle() {
        $releaseBranch = $this->argument('release-branch');
        $issues = $this->argument('issues');

        // Find the issue branches from the issue numbers
        

        // Merge the issue branches into the release branch
        exec("git checkout $releaseBranch");
        foreach ($issueBranches as $issueBranch) {
            exec("git merge $issueBranch");
        }

        // Increment the tag's deploy number
        exec("git fetch --tags");
        exec("git tag --sort=-version:refname | grep -E '^v[0-9]+\.[0-9]+\.[0-9]+$' | head -n 1", $latestTag);
        $latestTag = $latestTag[0];
        preg_match('/^v([0-9]+\.[0-9]+)\.([0-9]+)$/', $latestTag, $matches);
        $hotfixNumber = $matches[2];
        $newTag = "v{$matches[1]}." . ($hotfixNumber + 1);
        exec("git tag $newTag");

        // Push the branch and the tags
        exec("git push origin $releaseBranch");
        exec("git push origin $newTag");
    }
}
