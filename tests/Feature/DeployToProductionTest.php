<?php

it('works in a dummy git repo', function () {
    $this->artisan('deploy-to-production ' . $this->branchTwoName)
        ->expectsQuestion('Please type the name of the production branch to continue.', 'forge-production')
        ->expectsOutput('Deploying ' . $this->branchTwoName . ' to production.')
        ->expectsOutput('Checking out production branch.')
        ->expectsOutput('Pulling latest production branch.')
        ->expectsOutput('Merging ' . $this->branchTwoName . ' into production.')
        ->expectsOutput('Pushing production branch.')
        ->expectsOutput('Checking out dev branch.')
        ->expectsOutput('Pulling latest dev branch.')
        ->expectsOutput('Merging production into dev.')
        ->expectsOutput('Pushing dev branch.')
        ->expectsOutput('Deleting ' . $this->branchTwoName . '.')
        ->expectsOutput('Done!');
})->group('dummy-git-repo');

it('can ask for the branch to deploy', function () {
    $this->artisan('deploy-to-production')
        ->expectsQuestion('Please type the name of the production branch to continue.', 'forge-production')
        ->expectsQuestion('Which branch should we deploy?', $this->branchTwoName)
        ->expectsOutput('Deploying ' . $this->branchTwoName . ' to production.')
        ->expectsOutput('Checking out production branch.')
        ->expectsOutput('Pulling latest production branch.')
        ->expectsOutput('Merging ' . $this->branchTwoName . ' into production.')
        ->expectsOutput('Pushing production branch.')
        ->expectsOutput('Checking out dev branch.')
        ->expectsOutput('Pulling latest dev branch.')
        ->expectsOutput('Merging production into dev.')
        ->expectsOutput('Pushing dev branch.')
        ->expectsOutput('Deleting ' . $this->branchTwoName . '.')
        ->expectsOutput('Done!');
});