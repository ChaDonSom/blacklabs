<?php

use CzProject\GitPhp\Git;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

it('exits without a version or issues', function () {
    try {
        $this->artisan('create-release-branch');
    } catch (\Exception $e) {
        expect($e->getMessage())->toContain('Not enough arguments (missing: "version, issues")');
    }
});

it('exits with a version but no issues', function () {
    try {
        $this->artisan('create-release-branch 0.23.2');
    } catch (\Exception $e) {
        expect($e->getMessage())->toContain('Not enough arguments (missing: "issues")');
    }
});

it('works in a dummy git repo', function () {
    $this->artisan('create-release-branch 0.23.2 123,456')
        ->expectsOutput('Creating release branch for version 0.23.2.')
        ->expectsOutput('Checking out dev branch.')
        ->expectsOutput('Pulling latest dev branch.')
        ->expectsOutput('Creating release branch.')
        ->expectsOutput('Pulling issue branches into release branch.')
        ->expectsOutput('Finding branch for issue 123...')
        ->expectsOutput('Finding branch for issue 456...')
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

    $limitedBranchName = Str::limit($this->branchTwoName, 20, '...');
    $this->artisan('create-release-branch 0.23.2 123,456')
        ->expectsOutput('Creating release branch for version 0.23.2.')
        ->expectsOutput('Checking out dev branch.')
        ->expectsOutput('Pulling latest dev branch.')
        ->expectsOutput('Creating release branch.')
        ->expectsOutput('Pulling issue branches into release branch.')
        ->expectsOutput('Finding branch for issue 123...')
        ->expectsOutput('Finding branch for issue 456...')
        ->expectsOutput('Merge conflict detected with branch ' . $limitedBranchName . '. Please resolve manually, then continue when you\'ve committed the merge.')
        ->expectsConfirmation('Ready to continue?', 'no')
        ->run();
})->group('dummy-git-repo');

it('can delete the branch if instructed to', function () {
    $this->repo->createBranch('release/0.23.2-123-456');

    $this->artisan('create-release-branch 0.23.2 123,456')
        ->expectsOutput('Creating release branch for version 0.23.2.')
        ->expectsOutput('Checking out dev branch.')
        ->expectsOutput('Pulling latest dev branch.')
        ->expectsOutput('Creating release branch.')
        ->expectsQuestion('Branch release/0.23.2-123-456 already exists. Should we delete it?', 'yes')
        ->expectsOutput('Pulling issue branches into release branch.')
        ->expectsOutput('Finding branch for issue 123...')
        ->expectsOutput('Finding branch for issue 456...')
        ->expectsOutput('Pushing release branch to origin.')
        ->expectsOutput('Creating release PR.')
        ->expectsOutput('Creating release tag.')
        ->expectsOutput('Done.')
        ->expectsOutput('Branch: release/0.23.2-123-456')
        ->assertExitCode(0)
        ->run();
})->group('dummy-git-repo');

it('can skip missing issue branches', function() {
    $this->artisan('create-release-branch 0.23.2 123,324')
        ->expectsOutput('Creating release branch for version 0.23.2.')
        ->expectsOutput('Checking out dev branch.')
        ->expectsOutput('Pulling latest dev branch.')
        ->expectsOutput('Creating release branch.')
        ->expectsOutput('Pulling issue branches into release branch.')
        ->expectsOutput('Finding branch for issue 123...')
        ->expectsOutput('No branch found for issue 324. Please choose one, or skip this issue for now.')
        ->expectsQuestion('Choose a branch for issue 324', 'Skip')
        ->run();
})->group('dummy-git-repo');

it('can use provided missing issue branches', function () {
    $this->branchThreeName = 'different-name-' . Str::kebab(collect(fake()->words())->join('-'));
    $this->repo->createBranch($this->branchThreeName, true);
    touch('./README-324.md');
    $this->repo->addAllChanges();
    $this->repo->commit('324 commit');
    exec('git push --set-upstream origin ' . $this->branchThreeName);

    $this->artisan('create-release-branch 0.23.2 123,324')
        ->expectsOutput('Creating release branch for version 0.23.2.')
        ->expectsOutput('Checking out dev branch.')
        ->expectsOutput('Pulling latest dev branch.')
        ->expectsOutput('Creating release branch.')
        ->expectsOutput('Pulling issue branches into release branch.')
        ->expectsOutput('Finding branch for issue 123...')
        ->expectsOutput('Finding branch for issue 324...')
        ->expectsOutput('No branch found for issue 324. Please choose one, or skip this issue for now.')
        ->expectsQuestion('Choose a branch for issue 324', $this->branchThreeName)
        ->expectsOutput('Pushing release branch to origin.')
        ->expectsOutput('Creating release PR.')
        ->expectsOutput('Creating release tag.')
        ->expectsOutput('Done.')
        ->expectsOutput('Branch: release/0.23.2-123-324')
        ->assertExitCode(0)
        ->run();
})->group('dummy-git-repo');