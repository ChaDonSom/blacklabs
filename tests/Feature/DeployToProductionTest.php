<?php

it('works in a dummy git repo', function () {
    $this->artisan('devops:deploy-to-production ' . $this->branchTwoName)
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