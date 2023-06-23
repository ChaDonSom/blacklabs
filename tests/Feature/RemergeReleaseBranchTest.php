<?php

use Illuminate\Support\Str;

it('merges issue branches and increments the tag', function () {
    $this->artisan('create-release-branch 0.23.2 123,456');
    $branchTwoNameLimited = Str::limit($this->branchTwoName, 20, '...');
    $this->artisan('merge-and-increment-tag release/0.23.2-123-456 456')
        ->expectsOutput('Finding branch for issue 456...')
        ->expectsOutput('Merging ' . $branchTwoNameLimited . '...')
        ->expectsOutput('Incrementing the tag...')
        ->expectsOutput('New tag: 0.23.2.1')
        ->expectsOutput('Pushing the branch and the tag...')
        ->expectsOutput('Done!');
})->group('dummy-git-repo');

it('uses git tag', function () {
    $this->repo->createTag('0.23.2.5');
    $this->artisan('create-release-branch 0.23.2 123,456');
    $branchTwoNameLimited = Str::limit($this->branchTwoName, 20, '...');
    $this->artisan('merge-and-increment-tag release/0.23.2-123-456 456')
        ->expectsOutput('Finding branch for issue 456...')
        ->expectsOutput('Merging ' . $branchTwoNameLimited . '...')
        ->expectsOutput('Incrementing the tag...')
        ->expectsOutput('New tag: 0.23.2.6')
        ->expectsOutput('Pushing the branch and the tag...')
        ->expectsOutput('Done!');
})->group('dummy-git-repo');