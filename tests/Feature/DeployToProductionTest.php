<?php

function makeReleaseBranchForDeploy($originRepo, $repo, string $defaultBranch, string $branchName, string $fileName): void
{
    $originRepo->createBranch($branchName, true);
    touch("/tmp/test-repo-origin/{$fileName}");
    $originRepo->addAllChanges();
    $originRepo->commit("{$fileName} commit");
    $originRepo->checkout($defaultBranch);
    $repo->fetch();
    $repo->checkout($branchName);
    $repo->checkout($defaultBranch);
}

it('works in a dummy git repo', function () {
    $releaseBranch = 'release/v1.0.1/456';
    makeReleaseBranchForDeploy($this->originRepo, $this->repo, $this->defaultBranch, $releaseBranch, 'README-456-release.md');

    $this->artisan('deploy-to-production '.$releaseBranch)
        ->expectsQuestion('Please type the name of the production branch to continue.', 'forge-production')
        ->assertExitCode(0);
})->group('dummy-git-repo');

it('can ask for the branch to deploy', function () {
    $releaseBranch = 'release/v1.0.1/456';
    makeReleaseBranchForDeploy($this->originRepo, $this->repo, $this->defaultBranch, $releaseBranch, 'README-456-release.md');

    $this->artisan('deploy-to-production')
        ->expectsQuestion('Please type the name of the production branch to continue.', 'forge-production')
        ->expectsQuestion('Which branch should we deploy?', $releaseBranch)
        ->assertExitCode(0);
});

it('updates the version with patch', function () {
    $releaseBranch = 'release/v1.0.1/456';
    makeReleaseBranchForDeploy($this->originRepo, $this->repo, $this->defaultBranch, $releaseBranch, 'README-456-release.md');

    $this->artisan('deploy-to-production '.$releaseBranch)
        ->expectsQuestion('Please type the name of the production branch to continue.', 'forge-production')
        ->expectsOutput('Running patch from v1.0.0.')
        ->expectsOutput('New version: v1.0.1')
        ->assertExitCode(0);
});

it('rejects non-release branches', function () {
    $this->artisan('deploy-to-production '.$this->branchTwoName)
        ->expectsQuestion('Please type the name of the production branch to continue.', 'forge-production')
        ->expectsOutput('Only release branches can be deployed to production. Aborting.')
        ->assertExitCode(1);
});

it('updates the version with minor', function () {
    $this->branchFourName = 'release/v1.1.0/87678';
    makeReleaseBranchForDeploy($this->originRepo, $this->repo, $this->defaultBranch, $this->branchFourName, 'README-87678.md');

    $this->artisan('deploy-to-production '.$this->branchFourName)
        ->expectsQuestion('Please type the name of the production branch to continue.', 'forge-production')
        ->expectsOutput('Running minor from v1.0.0.')
        ->expectsOutput('New version: v1.1.0')
        ->assertExitCode(0);
});

it('updates the version with major', function () {
    $this->branchFourName = 'release/v2.0.0/73474';
    makeReleaseBranchForDeploy($this->originRepo, $this->repo, $this->defaultBranch, $this->branchFourName, 'README-73474.md');

    $this->artisan('deploy-to-production '.$this->branchFourName)
        ->expectsQuestion('Please type the name of the production branch to continue.', 'forge-production')
        ->expectsOutput('Running major from v1.0.0.')
        ->expectsOutput('New version: v2.0.0')
        ->assertExitCode(0);
});
