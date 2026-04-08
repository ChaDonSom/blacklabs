<?php

function makeReleaseBranchForDeploy($originRepo, $repo, string $defaultBranch, string $branchName, string $fileName, ?string $commitMessage = null): void
{
    $originRepo->createBranch($branchName, true);
    touch("/tmp/test-repo-origin/{$fileName}");
    $originRepo->addAllChanges();
    $originRepo->commit($commitMessage ?? "{$fileName} commit");
    $originRepo->checkout($defaultBranch);
    $repo->fetch();
    $repo->checkout($branchName);
    $repo->checkout($defaultBranch);
}

function setOriginBranchPackageVersionForDeploy($originRepo, string $defaultBranch, string $branchName, string $version): void
{
    $originRepo->checkout($branchName);

    $packageJsonPath = '/tmp/test-repo-origin/package.json';
    $packageJson = json_decode(file_get_contents($packageJsonPath), true);
    $packageJson['version'] = $version;

    file_put_contents($packageJsonPath, json_encode($packageJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);

    $originRepo->addAllChanges();
    $originRepo->commit("Set package version to {$version}");
    $originRepo->checkout($defaultBranch);
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
        ->expectsOutput('Setting version from v1.0.0 to v1.0.1.')
        ->expectsOutput('New version: v1.0.1')
        ->assertExitCode(0);
});

it('rejects non-release branches', function () {
    $this->artisan('deploy-to-production '.$this->branchTwoName)
        ->expectsQuestion('Please type the name of the production branch to continue.', 'forge-production')
        ->expectsOutput('Only release branches can be deployed to production. Aborting.')
        ->assertExitCode(1);
});

it('aborts when there are no release branches to choose from', function () {
    $this->artisan('deploy-to-production')
        ->expectsQuestion('Please type the name of the production branch to continue.', 'forge-production')
        ->expectsOutput('No release branches found. Aborting.')
        ->assertExitCode(1);
})->group('dummy-git-repo');

it('updates the version with minor', function () {
    $this->branchFourName = 'release/v1.1.0/87678';
    makeReleaseBranchForDeploy($this->originRepo, $this->repo, $this->defaultBranch, $this->branchFourName, 'README-87678.md', '87678 commit');

    $this->artisan('deploy-to-production '.$this->branchFourName)
        ->expectsQuestion('Please type the name of the production branch to continue.', 'forge-production')
        ->expectsOutput('Setting version from v1.0.0 to v1.1.0.')
        ->expectsOutput('New version: v1.1.0')
        ->assertExitCode(0);
});

it('updates the version with major', function () {
    $this->branchFourName = 'release/v2.0.0/73474';
    makeReleaseBranchForDeploy($this->originRepo, $this->repo, $this->defaultBranch, $this->branchFourName, 'README-73474.md', '73474 commit');

    $this->artisan('deploy-to-production '.$this->branchFourName)
        ->expectsQuestion('Please type the name of the production branch to continue.', 'forge-production')
        ->expectsOutput('Setting version from v1.0.0 to v2.0.0.')
        ->expectsOutput('New version: v2.0.0')
        ->assertExitCode(0);
});

it('updates the version with multi-digit minor versions', function () {
    setOriginBranchPackageVersionForDeploy($this->originRepo, $this->defaultBranch, 'forge-production', '0.53.5');

    $this->branchFourName = 'release/v0.54.0-0/2493-2503-2096-2440-2486';
    makeReleaseBranchForDeploy($this->originRepo, $this->repo, $this->defaultBranch, $this->branchFourName, 'README-2493.md', '2493 commit');

    $this->artisan('deploy-to-production '.$this->branchFourName)
        ->expectsQuestion('Please type the name of the production branch to continue.', 'forge-production')
        ->expectsOutput('Setting version from v0.53.5 to v0.54.0.')
        ->expectsOutput('New version: v0.54.0')
        ->assertExitCode(0);
});

it('fails when the release version does not increase', function () {
    $this->branchFourName = 'release/v1.0.0/77777';
    makeReleaseBranchForDeploy($this->originRepo, $this->repo, $this->defaultBranch, $this->branchFourName, 'README-77777.md', '77777 commit');

    $this->artisan('deploy-to-production '.$this->branchFourName)
        ->expectsQuestion('Please type the name of the production branch to continue.', 'forge-production')
        ->expectsOutput('Release version v1.0.0 must be greater than current version v1.0.0.')
        ->assertExitCode(1);
});

it('fails when the current version cannot be parsed', function () {
    setOriginBranchPackageVersionForDeploy($this->originRepo, $this->defaultBranch, 'forge-production', '1.0');

    $this->branchFourName = 'release/v1.0.1/88888';
    makeReleaseBranchForDeploy($this->originRepo, $this->repo, $this->defaultBranch, $this->branchFourName, 'README-88888.md', '88888 commit');

    $this->artisan('deploy-to-production '.$this->branchFourName)
        ->expectsQuestion('Please type the name of the production branch to continue.', 'forge-production')
        ->expectsOutput('Unable to determine version bump from v1.0 to v1.0.1.')
        ->assertExitCode(1);
});

it('fails when the release branch name does not contain a supported version format', function () {
    $this->branchFourName = 'release/not-a-version/99999';
    makeReleaseBranchForDeploy($this->originRepo, $this->repo, $this->defaultBranch, $this->branchFourName, 'README-99999.md', '99999 commit');

    $this->artisan('deploy-to-production '.$this->branchFourName)
        ->expectsQuestion('Please type the name of the production branch to continue.', 'forge-production')
        ->expectsOutput('Unable to determine version bump from branch release/not-a-version/99999.')
        ->assertExitCode(1);
});

it('deletes the release branch worktree when the worktree is clean', function () {
    $releaseBranch = 'release/v1.0.1/456';
    makeReleaseBranchForDeploy($this->originRepo, $this->repo, $this->defaultBranch, $releaseBranch, 'README-456-release.md');

    $worktreePath = '/tmp/test-worktree';
    exec('git -C /tmp/test-repo worktree add ' . escapeshellarg($worktreePath) . ' ' . escapeshellarg($releaseBranch));

    $this->artisan('deploy-to-production ' . $releaseBranch)
        ->expectsQuestion('Please type the name of the production branch to continue.', 'forge-production')
        ->assertExitCode(0);

    $branchListOutput = shell_exec('git -C /tmp/test-repo branch --list ' . escapeshellarg($releaseBranch));

    expect(is_dir($worktreePath))->toBeFalse()
        ->and(trim((string) $branchListOutput))->toBe('');
})->group('dummy-git-repo');

it('warns and skips local deletion when the release branch worktree has unsaved changes', function () {
    $releaseBranch = 'release/v1.0.1/456';
    makeReleaseBranchForDeploy($this->originRepo, $this->repo, $this->defaultBranch, $releaseBranch, 'README-456-release.md');

    $worktreePath = '/tmp/test-worktree';
    exec('git -C /tmp/test-repo worktree add ' . escapeshellarg($worktreePath) . ' ' . escapeshellarg($releaseBranch));
    // Create an untracked file to make the worktree dirty
    touch($worktreePath . '/dirty-file.txt');

    $this->artisan('deploy-to-production ' . $releaseBranch)
        ->expectsQuestion('Please type the name of the production branch to continue.', 'forge-production')
        ->expectsOutput("Branch '{$releaseBranch}' is checked out at '{$worktreePath}' with unsaved changes. Leaving it here for you.")
        ->assertExitCode(0);

    $localBranchExitCode = null;
    exec(
        'git -C /tmp/test-repo show-ref --verify --quiet ' . escapeshellarg("refs/heads/{$releaseBranch}"),
        $localBranchOutput,
        $localBranchExitCode
    );

    $remoteBranchExitCode = null;
    exec(
        'git -C /tmp/test-repo show-ref --verify --quiet ' . escapeshellarg("refs/remotes/origin/{$releaseBranch}"),
        $remoteBranchOutput,
        $remoteBranchExitCode
    );

    expect(is_dir($worktreePath))->toBeTrue()
        ->and($localBranchExitCode)->toBe(0)
        ->and($remoteBranchExitCode)->toBe(0);
})->group('dummy-git-repo');

it('deploys to the exact skipped version when versions are not sequential', function () {
    // Simulate a scenario where v1.1.0 was skipped (another release took it) and we deploy v1.2.0.
    // With exact-version deployment, production should end up at v1.2.0, not v1.1.0.
    $this->branchFourName = 'release/v1.2.0-0/55555';
    makeReleaseBranchForDeploy($this->originRepo, $this->repo, $this->defaultBranch, $this->branchFourName, 'README-55555.md', '55555 commit');

    $this->artisan('deploy-to-production '.$this->branchFourName)
        ->expectsQuestion('Please type the name of the production branch to continue.', 'forge-production')
        ->expectsOutput('Setting version from v1.0.0 to v1.2.0.')
        ->expectsOutput('New version: v1.2.0')
        ->assertExitCode(0);
})->group('dummy-git-repo');
