<?php

it('warns if you pick production', function () {
    $this->artisan('devops:update-site-branch-and-deploy blacklabsconsole.com forge-production')
        ->expectsOutput('[WARNING] You are about to deploy to production!')
        ->expectsQuestion('Please type the name of the site to continue.', '')
        ->expectsOutput('You didn\'t type the name of the site correctly. Aborting.')
        ->assertExitCode(1);
    $this->artisan('devops:update-site-branch-and-deploy')
        ->expectsQuestion('Which site would you like to update and deploy?', 'blacklabsconsole.com')
        ->expectsOutput('[WARNING] You are about to deploy to production!')
        ->expectsQuestion('Please type the name of the site to continue.', '')
        ->expectsOutput('You didn\'t type the name of the site correctly. Aborting.')
        ->assertExitCode(1);
});