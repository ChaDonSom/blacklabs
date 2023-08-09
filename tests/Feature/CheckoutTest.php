<?php

namespace Tests\Feature;

it('runs', function () {
    $this->artisan('checkout ' . $this->branchTwoName)
        ->expectsOutput("Checking for migrations in current branch first...")
        ->expectsOutput("\nChecking out to {$this->branchTwoName}...")
        ->expectsOutput("\nDone!")
        ->assertExitCode(0);
});
