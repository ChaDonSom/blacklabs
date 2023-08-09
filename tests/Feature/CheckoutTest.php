<?php

namespace Tests\Feature;

it('runs', function () {
    $this->artisan('checkout ' . $this->branchTwoName)
        ->expectsOutput("Checking for migrations in current branch first...")
        ->expectsOutput("Checking out to {$this->branchTwoName}...")
        ->expectsOutput("Done!")
        ->assertExitCode(0);
});
