<?php

use Illuminate\Support\Str;

it('merges issue branches and increments the tag', function () {
    $this->artisan('create-release-branch premajor 123,456');
    $branchTwoNameLimited = Str::limit($this->branchTwoName, 20, '...');
    $this->artisan('remerge-release-branch 456 release/v2.0.0-0/123-456')
        ->expectsOutput('Finding branch for issue 456...')
        ->expectsOutput('Merging ' . $branchTwoNameLimited . '...')
        ->expectsOutput('Pushing the branch and the tag...')
        ->expectsOutput('Done!');
})->group('dummy-git-repo');
