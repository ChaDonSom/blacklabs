<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;

it('proceeds normally when the branch is not in a worktree', function () {
    $this->artisan('checkout ' . $this->branchTwoName)
        ->expectsOutput("Checking for migrations in current branch first...")
        ->expectsOutput("\nChecking out to {$this->branchTwoName}...")
        ->expectsOutput("\nDone!")
        ->assertExitCode(0);
})->group('dummy-git-repo');

it('asks to switch to an existing worktree when the branch is already checked out', function () {
    $worktreePath = '/tmp/test-worktree';
    exec('git worktree add ' . escapeshellarg($worktreePath) . ' ' . escapeshellarg($this->branchTwoName));

    $this->artisan('checkout ' . $this->branchTwoName)
        ->expectsOutput("Checking for migrations in current branch first...")
        ->expectsOutput("\nChecking out to {$this->branchTwoName}...")
        ->expectsQuestion(
            "Branch '{$this->branchTwoName}' is already checked out at '{$worktreePath}'. Would you like to switch there?",
            'no'
        )
        ->assertExitCode(1);
})->group('dummy-git-repo');

it('switches to an existing worktree when the user accepts', function () {
    $worktreePath = '/tmp/test-worktree';
    exec('git worktree add ' . escapeshellarg($worktreePath) . ' ' . escapeshellarg($this->branchTwoName));

    $this->artisan('checkout ' . $this->branchTwoName)
        ->expectsOutput("Checking for migrations in current branch first...")
        ->expectsOutput("\nChecking out to {$this->branchTwoName}...")
        ->expectsQuestion(
            "Branch '{$this->branchTwoName}' is already checked out at '{$worktreePath}'. Would you like to switch there?",
            'yes'
        )
        ->expectsQuestion('Remember this choice for future checkouts?', 'no')
        ->expectsOutput("Switched to worktree at {$worktreePath}.")
        ->expectsOutput("\nDone!")
        ->assertExitCode(0);
})->group('dummy-git-repo');

it('automatically switches to a worktree when the preference is set to always', function () {
    $worktreePath = '/tmp/test-worktree';
    exec('git worktree add ' . escapeshellarg($worktreePath) . ' ' . escapeshellarg($this->branchTwoName));
    Storage::put('worktree-preference.txt', 'always');

    $this->artisan('checkout ' . $this->branchTwoName)
        ->expectsOutput("Checking for migrations in current branch first...")
        ->expectsOutput("\nChecking out to {$this->branchTwoName}...")
        ->expectsOutput("Switched to worktree at {$worktreePath}.")
        ->expectsOutput("\nDone!")
        ->assertExitCode(0);
})->group('dummy-git-repo');

it('saves the worktree preference when the user chooses to remember', function () {
    $worktreePath = '/tmp/test-worktree';
    exec('git worktree add ' . escapeshellarg($worktreePath) . ' ' . escapeshellarg($this->branchTwoName));

    $this->artisan('checkout ' . $this->branchTwoName)
        ->expectsOutput("Checking for migrations in current branch first...")
        ->expectsOutput("\nChecking out to {$this->branchTwoName}...")
        ->expectsQuestion(
            "Branch '{$this->branchTwoName}' is already checked out at '{$worktreePath}'. Would you like to switch there?",
            'yes'
        )
        ->expectsQuestion('Remember this choice for future checkouts?', 'yes')
        ->expectsOutput('Worktree preference saved. Future checkouts will automatically switch to existing worktrees.')
        ->expectsOutput("Switched to worktree at {$worktreePath}.")
        ->expectsOutput("\nDone!")
        ->assertExitCode(0);

    expect(Storage::get('worktree-preference.txt'))->toBe('always');
})->group('dummy-git-repo');

it('fails without prompting in non-interactive mode when no preference is set', function () {
    $worktreePath = '/tmp/test-worktree';
    exec('git worktree add ' . escapeshellarg($worktreePath) . ' ' . escapeshellarg($this->branchTwoName));

    $this->artisan('checkout ' . $this->branchTwoName . ' --no-interaction')
        ->expectsOutput("Checking for migrations in current branch first...")
        ->expectsOutput("\nChecking out to {$this->branchTwoName}...")
        ->assertExitCode(1);
})->group('dummy-git-repo');

it('auto-switches in non-interactive mode when preference is always', function () {
    $worktreePath = '/tmp/test-worktree';
    exec('git worktree add ' . escapeshellarg($worktreePath) . ' ' . escapeshellarg($this->branchTwoName));
    Storage::put('worktree-preference.txt', 'always');

    $this->artisan('checkout ' . $this->branchTwoName . ' --no-interaction')
        ->expectsOutput("Checking for migrations in current branch first...")
        ->expectsOutput("\nChecking out to {$this->branchTwoName}...")
        ->expectsOutput("Switched to worktree at {$worktreePath}.")
        ->expectsOutput("\nDone!")
        ->assertExitCode(0);
})->group('dummy-git-repo');
