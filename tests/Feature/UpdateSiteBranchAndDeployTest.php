<?php

it('warns if you pick production', function () {
    fakeForgeApi('release/v1.0.0/123', 'blacklabsconsole.com');
    $this->artisan('update-site-branch-and-deploy blacklabsconsole.com forge-production')
        ->expectsOutput('[WARNING] You are about to deploy to production! This will change the branch of production away from forge-production!')
        ->expectsQuestion('Please type the name of the site to continue.', '')
        ->expectsOutput('You didn\'t type the name of the site correctly. Aborting.')
        ->assertExitCode(1);
    fakeForgeApi('release/v1.0.0/123', 'blacklabsconsole.com');
    $this->artisan('update-site-branch-and-deploy blacklabsconsole.com')
        // ->expectsQuestion('Which site would you like to update and deploy?', 'blacklabsconsole.com')
        ->expectsOutput('[WARNING] You are about to deploy to production! This will change the branch of production away from forge-production!')
        ->expectsQuestion('Please type the name of the site to continue.', '')
        ->expectsOutput('You didn\'t type the name of the site correctly. Aborting.')
        ->assertExitCode(1);
});
