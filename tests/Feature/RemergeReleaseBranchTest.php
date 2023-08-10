<?php

use Illuminate\Support\Str;

it('merges issue branches and increments the tag', function () {
    $this->artisan('create-release-branch 0.23.2 123,456');
    $branchTwoNameLimited = Str::limit($this->branchTwoName, 20, '...');
    $this->artisan('merge-and-increment-tag release/v0.23.2-123-456 456')
        ->expectsOutput('Finding branch for issue 456...')
        ->expectsOutput('Merging ' . $branchTwoNameLimited . '...')
        ->expectsOutput('Incrementing the tag...')
        ->expectsOutput('New tag: v0.23.2.1')
        ->expectsOutput('Pushing the branch and the tag...')
        ->expectsOutput('Done!');
})->group('dummy-git-repo');

it('uses git tag', function () {
    $this->repo->createTag('v0.23.2.5');
    $this->artisan('create-release-branch 0.23.2 123,456');
    $branchTwoNameLimited = Str::limit($this->branchTwoName, 20, '...');
    $this->artisan('merge-and-increment-tag release/v0.23.2-123-456 456')
        ->expectsOutput('Finding branch for issue 456...')
        ->expectsOutput('Merging ' . $branchTwoNameLimited . '...')
        ->expectsOutput('Incrementing the tag...')
        ->expectsOutput('New tag: v0.23.2.6')
        ->expectsOutput('Pushing the branch and the tag...')
        ->expectsOutput('Done!');
})->group('dummy-git-repo');

it('doesnt add vs from the tag', function () {
    $this->artisan('create-release-branch v0.23.2 123,456')
        ->expectsOutput('Branch: release/v0.23.2-123-456')
        ->assertExitCode(0)
        ->run();
    // Then also check the tag it created and make sure the tag has only one 'v'
    $this->artisan('merge-and-increment-tag release/v0.23.2-123-456 456')
        ->expectsOutput('Finding branch for issue 456...')
        ->expectsOutput('Incrementing the tag...')
        ->expectsOutput('New tag: v0.23.2.1')
        ->expectsOutput('Pushing the branch and the tag...')
        ->expectsOutput('Done!');
})->group('dummy-git-repo');