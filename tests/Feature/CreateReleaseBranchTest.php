<?php

use CzProject\GitPhp\Git;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

it('exits without a version or issues', function () {
    try {
        $this->artisan('devops:create-release-branch');
    } catch (\Exception $e) {
        expect($e->getMessage())->toContain('Not enough arguments (missing: "version, issues")');
    }
});

it('exits with a version but no issues', function () {
    try {
        $this->artisan('devops:create-release-branch 0.23.2');
    } catch (\Exception $e) {
        expect($e->getMessage())->toContain('Not enough arguments (missing: "issues")');
    }
});

uses()->group('dummy-git-repo')->beforeEach(function () {
    $this->git = app()->make(Git::class);
    // Force remove the directory
    exec('rm -rf /tmp/test-repo');
    exec('rm -rf /tmp/test-repo-origin');
    $this->repo = $this->git->init('/tmp/test-repo-origin');
    chdir('/tmp/test-repo-origin');
    touch('./README.md');
    $this->repo->addAllChanges();
    $this->repo->commit('Initial commit');
    $this->repo->createBranch('dev', true);
    file_put_contents('./README.md', 'dev');
    $this->repo->addAllChanges();
    $this->repo->commit('Initial dev commit');
    // Create two branches
    $this->branchOneName = '123-' . Str::kebab(collect(fake()->words())->join('-'));
    $this->repo->createBranch($this->branchOneName, true);
    touch('./README-123.md');
    $this->repo->addAllChanges();
    $this->repo->commit('123 commit');
    $this->repo->checkout('dev');
    $this->branchTwoName = '456-' . Str::kebab(collect(fake()->words())->join('-'));
    $this->repo->createBranch($this->branchTwoName, true);
    touch('./README-456.md');
    $this->repo->addAllChanges();
    $this->repo->commit('456 commit');

    $this->repo = $this->git->cloneRepository('/tmp/test-repo-origin', '/tmp/test-repo');
    chdir('/tmp/test-repo');
})->afterEach(function () {
    exec('rm -rf /tmp/test-repo');
    exec('rm -rf /tmp/test-repo-origin');
});

it('works in a dummy git repo', function () {
    $this->artisan('devops:create-release-branch 0.23.2 123,456')
        ->expectsOutput('Creating release branch for version 0.23.2.')
        ->expectsOutput('Checking out dev branch.')
        ->expectsOutput('Pulling latest dev branch.')
        ->expectsOutput('Creating release branch.')
        ->expectsOutput('Pulling issue branches into release branch.')
        ->expectsOutput('Merging issue 123 into release branch.')
        ->expectsOutput('Merging issue 456 into release branch.')
        ->expectsOutput('Pushing release branch to origin.')
        ->expectsOutput('Creating release PR.')
        ->expectsOutput('Creating release tag.')
        ->expectsOutput('Done.')
        ->expectsOutput('Branch: release/0.23.2-123-456')
        ->assertExitCode(0)
        ->run();
})->group('dummy-git-repo');

it('asks to continue when hitting merge conflicts', function () {
    $originRepo = $this->git->open('/tmp/test-repo-origin');
    $originRepo->checkout($this->branchOneName);
    chdir('/tmp/test-repo-origin');
    file_put_contents('./test-file-conflict.md', '123');
    $originRepo->addAllChanges();
    $originRepo->commit('123 commit');
    $originRepo->checkout($this->branchTwoName);
    file_put_contents('./test-file-conflict.md', '456');
    $originRepo->addAllChanges();
    $originRepo->commit('456 commit');
    $originRepo->checkout('dev');
    chdir('/tmp/test-repo');

    $this->artisan('devops:create-release-branch 0.23.2 123,456')
        ->expectsOutput('Creating release branch for version 0.23.2.')
        ->expectsOutput('Checking out dev branch.')
        ->expectsOutput('Pulling latest dev branch.')
        ->expectsOutput('Creating release branch.')
        ->expectsOutput('Pulling issue branches into release branch.')
        ->expectsOutput('Merging issue 123 into release branch.')
        ->expectsOutput('Merging issue 456 into release branch.')
        ->expectsOutput('Merge conflict detected with issue 456. Please resolve manually, then continue when you\'ve committed the merge.')
        ->expectsConfirmation('Ready to continue?', 'no')
        ->run();
})->group('dummy-git-repo');

it('can delete the branch if instructed to', function () {
    $this->repo->createBranch('release/0.23.2-123-456');

    $this->artisan('devops:create-release-branch 0.23.2 123,456')
        ->expectsOutput('Creating release branch for version 0.23.2.')
        ->expectsOutput('Checking out dev branch.')
        ->expectsOutput('Pulling latest dev branch.')
        ->expectsOutput('Creating release branch.')
        ->expectsQuestion('Branch release/0.23.2-123-456 already exists. Should we delete it?', 'yes')
        ->expectsOutput('Pulling issue branches into release branch.')
        ->expectsOutput('Merging issue 123 into release branch.')
        ->expectsOutput('Merging issue 456 into release branch.')
        ->expectsOutput('Pushing release branch to origin.')
        ->expectsOutput('Creating release PR.')
        ->expectsOutput('Creating release tag.')
        ->expectsOutput('Done.')
        ->expectsOutput('Branch: release/0.23.2-123-456')
        ->assertExitCode(0)
        ->run();
})->group('dummy-git-repo');