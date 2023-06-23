<?php

use Illuminate\Support\Facades\Artisan;

it('merges issue branches and increments the tag', function () {
    // Add some tags to the repository
    exec("git tag v1.0.0");
    exec("git tag v1.1.0");
    
    // Create a new release branch and some issue branches
    $releaseBranch = 'release-1.0';
    $issueBranches = ['issue-1', 'issue-2'];
    exec("git checkout -b $releaseBranch");
    foreach ($issueBranches as $issueBranch) {
        exec("git checkout -b $issueBranch");
        exec("touch $issueBranch.txt");
        exec("git add $issueBranch.txt");
        exec("git commit -m 'Add $issueBranch.txt'");
        exec("git push origin $issueBranch");
    }

    // Run the command
    Artisan::call('merge-and-increment-tag', [
        'release-branch' => $releaseBranch,
        'issue-branches' => $issueBranches,
    ]);

    // Assert that the release branch and the new tag were pushed to the remote repository
    exec("git fetch origin $releaseBranch");
    exec("git fetch --tags");
    $output = exec("git tag --list $releaseBranch*");
    expect($output)->toContain("$releaseBranch");
    expect($output)->toContain("$releaseBranch.1");
})->group('dummy-git-repo');