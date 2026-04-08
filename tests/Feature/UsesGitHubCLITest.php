<?php

use App\Services\UsesGitHubCLI;
use Illuminate\Support\Facades\Process;

// Create a concrete class that uses the trait so we can test it directly
beforeEach(function () {
    $this->cli = new class
    {
        use UsesGitHubCLI;
    };
});

it('returns a linked branch from linkedBranches', function () {
    Process::fake([
        'git config --get remote.origin.url' => Process::result('https://github.com/owner/repo.git'),
        'gh api graphql*' => Process::result('my-linked-branch'),
    ]);

    $result = $this->cli->getBranchForIssue(42);

    expect($result)->toBe('my-linked-branch');
});

it('returns a branch from closingPullRequests when linkedBranches is empty', function () {
    // When the jq `//` operator falls through from linkedBranches to closingPullRequests,
    // the gh CLI still returns a single branch name string.
    Process::fake([
        'git config --get remote.origin.url' => Process::result('https://github.com/owner/repo.git'),
        'gh api graphql*' => Process::result('copilot/add-issue-button'),
    ]);

    $result = $this->cli->getBranchForIssue(25);

    expect($result)->toBe('copilot/add-issue-button');
});

it('returns null when neither linkedBranches nor closingPullRequests has a branch', function () {
    Process::fake([
        'git config --get remote.origin.url' => Process::result('https://github.com/owner/repo.git'),
        'gh api graphql*' => Process::result('null'),
    ]);

    $result = $this->cli->getBranchForIssue(99);

    expect($result)->toBeNull();
});

it('returns null when gh api graphql returns empty output', function () {
    Process::fake([
        'git config --get remote.origin.url' => Process::result('https://github.com/owner/repo.git'),
        'gh api graphql*' => Process::result(''),
    ]);

    $result = $this->cli->getBranchForIssue(99);

    expect($result)->toBeNull();
});

it('returns null for non-numeric issue numbers', function () {
    $result = $this->cli->getBranchForIssue('abc');

    expect($result)->toBeNull();
});

it('returns null when git remote is not a GitHub URL', function () {
    Process::fake([
        'git config --get remote.origin.url' => Process::result('https://gitlab.com/owner/repo.git'),
    ]);

    $result = $this->cli->getBranchForIssue(42);

    expect($result)->toBeNull();
});

it('includes closingPullRequests in the GraphQL query', function () {
    Process::fake([
        'git config --get remote.origin.url' => Process::result('https://github.com/owner/repo.git'),
        'gh api graphql*' => Process::result('some-branch'),
    ]);

    $this->cli->getBranchForIssue(10);

    // Verify the gh api graphql command was called and includes the jq fallback expression
    Process::assertRan(function ($process) {
        $command = $process->command;

        if (! str_contains($command, 'gh api graphql')) {
            return false;
        }

        // Verify the jq expression includes the closingPullRequests fallback
        return str_contains($command, 'closingPullRequests');
    });
});
