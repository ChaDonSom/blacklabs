<?php

use Illuminate\Support\Facades\Log;

it('works in a dummy git repo', function () {
    $this->artisan('deploy-to-production ' . $this->branchTwoName)
        ->expectsQuestion('Please type the name of the production branch to continue.', 'forge-production')
        ->assertExitCode(0);
})->group('dummy-git-repo');

it('can ask for the branch to deploy', function () {
    $this->artisan('deploy-to-production')
        ->expectsQuestion('Please type the name of the production branch to continue.', 'forge-production')
        ->expectsQuestion('Which branch should we deploy?', $this->branchTwoName)
        ->assertExitCode(0);
});

it('updates the version with patch', function () {
    $this->artisan('deploy-to-production ' . $this->branchTwoName)
        ->expectsQuestion('Please type the name of the production branch to continue.', 'forge-production')
        ->expectsOutput('Running patch from v1.0.0.')
        ->expectsOutput('New version: v1.0.1')
        ->assertExitCode(0);
});

it('updates the version with minor', function () {
    $this->branchFourName = 'release/v1.1.0/87678';
    $this->originRepo->createBranch($this->branchFourName, true);
    touch('/tmp/test-repo-origin/README-87678.md');
    $this->originRepo->addAllChanges();
    $this->originRepo->commit('87678 commit');
    $this->originRepo->checkout($this->defaultBranch);
    $this->repo->fetch();
    $this->repo->checkout($this->branchFourName);
    $this->repo->checkout($this->defaultBranch);

    $this->artisan('deploy-to-production ' . $this->branchFourName)
        ->expectsQuestion('Please type the name of the production branch to continue.', 'forge-production')
        ->expectsOutput('Running minor from v1.0.0.')
        ->expectsOutput('New version: v1.1.0')
        ->assertExitCode(0);
});

it('updates the version with major', function () {
    $this->branchFourName = 'release/v2.0.0/73474';
    $this->originRepo->createBranch($this->branchFourName, true);
    touch('/tmp/test-repo-origin/README-73474.md');
    $this->originRepo->addAllChanges();
    $this->originRepo->commit('73474 commit');
    $this->originRepo->checkout($this->defaultBranch);
    $this->repo->fetch();
    $this->repo->checkout($this->branchFourName);
    $this->repo->checkout($this->defaultBranch);

    $this->artisan('deploy-to-production ' . $this->branchFourName)
        ->expectsQuestion('Please type the name of the production branch to continue.', 'forge-production')
        ->expectsOutput('Running major from v1.0.0.')
        ->expectsOutput('New version: v2.0.0')
        ->assertExitCode(0);
});
