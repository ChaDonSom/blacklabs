<?php

beforeEach(function () {
    $this->originPackageJsonPath = '/tmp/test-repo-origin/package.json';
    $this->createTestReleaseBranch = function (string $branchName, string $readmePath, string $commitMessage): void {
        $this->originRepo->createBranch($branchName, true);
        touch($readmePath);
        $this->originRepo->addAllChanges();
        $this->originRepo->commit($commitMessage);
        $this->originRepo->checkout('main');
        $this->repo->fetch();
        $this->repo->checkout($branchName);
        $this->repo->checkout('main');
    };
    $this->setTestOriginBranchPackageVersion = function (string $branchName, string $version): void {
        $this->originRepo->checkout($branchName);

        $packageJson = json_decode(file_get_contents($this->originPackageJsonPath), true);
        $packageJson['version'] = $version;

        file_put_contents($this->originPackageJsonPath, json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

        $this->originRepo->addAllChanges();
        $this->originRepo->commit("Set package version to {$version}");
        $this->originRepo->checkout('main');
    };
});

it('works in a dummy git repo', function () {
    $this->artisan('deploy-to-production '.$this->branchTwoName)
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
    $this->artisan('deploy-to-production '.$this->branchTwoName)
        ->expectsQuestion('Please type the name of the production branch to continue.', 'forge-production')
        ->expectsOutput('Running patch from v1.0.0.')
        ->expectsOutput('New version: v1.0.1')
        ->assertExitCode(0);
});

it('updates the version with minor', function () {
    $this->branchFourName = 'release/v1.1.0/87678';
    ($this->createTestReleaseBranch)($this->branchFourName, '/tmp/test-repo-origin/README-87678.md', '87678 commit');

    $this->artisan('deploy-to-production '.$this->branchFourName)
        ->expectsQuestion('Please type the name of the production branch to continue.', 'forge-production')
        ->expectsOutput('Running minor from v1.0.0.')
        ->expectsOutput('New version: v1.1.0')
        ->assertExitCode(0);
});

it('updates the version with major', function () {
    $this->branchFourName = 'release/v2.0.0/73474';
    ($this->createTestReleaseBranch)($this->branchFourName, '/tmp/test-repo-origin/README-73474.md', '73474 commit');

    $this->artisan('deploy-to-production '.$this->branchFourName)
        ->expectsQuestion('Please type the name of the production branch to continue.', 'forge-production')
        ->expectsOutput('Running major from v1.0.0.')
        ->expectsOutput('New version: v2.0.0')
        ->assertExitCode(0);
});

it('updates the version with multi-digit minor versions', function () {
    ($this->setTestOriginBranchPackageVersion)('forge-production', '0.53.5');

    $this->branchFourName = 'release/v0.54.0-0/2493-2503-2096-2440-2486';
    ($this->createTestReleaseBranch)($this->branchFourName, '/tmp/test-repo-origin/README-2493.md', '2493 commit');

    $this->artisan('deploy-to-production '.$this->branchFourName)
        ->expectsQuestion('Please type the name of the production branch to continue.', 'forge-production')
        ->expectsOutput('Running minor from v0.53.5.')
        ->expectsOutput('New version: v0.54.0')
        ->assertExitCode(0);
});

it('fails when the release version does not increase', function () {
    $this->branchFourName = 'release/v1.0.0/77777';
    ($this->createTestReleaseBranch)($this->branchFourName, '/tmp/test-repo-origin/README-77777.md', '77777 commit');

    $this->artisan('deploy-to-production '.$this->branchFourName)
        ->expectsQuestion('Please type the name of the production branch to continue.', 'forge-production')
        ->expectsOutput('Release version v1.0.0 must be greater than current version v1.0.0.')
        ->assertExitCode(1);
});

it('fails when the current version cannot be parsed', function () {
    ($this->setTestOriginBranchPackageVersion)('forge-production', '1.0');

    $this->branchFourName = 'release/v1.0.1/88888';
    ($this->createTestReleaseBranch)($this->branchFourName, '/tmp/test-repo-origin/README-88888.md', '88888 commit');

    $this->artisan('deploy-to-production '.$this->branchFourName)
        ->expectsQuestion('Please type the name of the production branch to continue.', 'forge-production')
        ->expectsOutput('Unable to determine version bump from v1.0 to v1.0.1.')
        ->assertExitCode(1);
});

it('fails when the release branch name does not contain a supported version format', function () {
    $this->branchFourName = 'release/not-a-version/99999';
    ($this->createTestReleaseBranch)($this->branchFourName, '/tmp/test-repo-origin/README-99999.md', '99999 commit');

    $this->artisan('deploy-to-production '.$this->branchFourName)
        ->expectsQuestion('Please type the name of the production branch to continue.', 'forge-production')
        ->expectsOutput('Unable to determine version bump from branch release/not-a-version/99999.')
        ->assertExitCode(1);
});
