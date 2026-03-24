<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

trait ManagesGitWorktrees
{
    /**
     * Try to check out the given branch. If the checkout fails because the branch
     * is already checked out in a worktree, offer to switch to that worktree
     * (interactive) or automatically switch if the worktree preference is 'always'.
     *
     * In non-interactive mode (--no-interaction) this falls back to the original
     * failure unless the stored preference is 'always', because confirm() uses its
     * default value (false) when input is non-interactive.
     *
     * @return bool True if a worktree switch occurred; false if a normal checkout happened.
     * @throws \Exception If the checkout fails and no worktree switch occurs.
     */
    public function checkoutBranch(string $branch): bool
    {
        try {
            $this->runProcess('git checkout ' . escapeshellarg($branch));

            return false;
        } catch (\Exception $e) {
            $worktreePath = $this->parseWorktreePath($e->getMessage());

            if ($worktreePath !== null && $this->shouldSwitchToWorktree($branch, $worktreePath)) {
                if (! is_dir($worktreePath)) {
                    $this->error("Git worktree path '{$worktreePath}' does not exist; falling back to original checkout failure.");
                    throw $e;
                }

                if (! chdir($worktreePath)) {
                    $this->error("Failed to switch to git worktree at '{$worktreePath}'; falling back to original checkout failure.");
                    throw $e;
                }

                $this->info("Switched to worktree at {$worktreePath}.");

                return true;
            }

            throw $e;
        }
    }

    /**
     * Determine whether to switch to the existing worktree for the given branch.
     * In non-interactive mode, confirm() uses its default value (false), so no
     * prompt is shown and this returns false (falling back to the original failure).
     */
    protected function shouldSwitchToWorktree(string $branch, string $worktreePath): bool
    {
        if ($this->getWorktreePreference() === 'always') {
            return true;
        }

        $switch = $this->confirm(
            "Branch '{$branch}' is already checked out at '{$worktreePath}'. Would you like to switch there?",
            false
        );

        if ($switch && $this->confirm('Remember this choice for future checkouts?', false)) {
            Storage::put('worktree-preference.txt', 'always');
            $this->info('Worktree preference saved. Future checkouts will automatically switch to existing worktrees.');
        }

        return $switch;
    }

    /**
     * Parse the worktree path from a git worktree conflict error message.
     * Handles two formats depending on git version:
     *   - git < 2.53: fatal: 'branch-name' is already checked out at '/path/to/worktree'
     *   - git >= 2.53: fatal: 'branch-name' is already used by worktree at '/path/to/worktree'
     */
    protected function parseWorktreePath(string $error): ?string
    {
        if (preg_match("/already (?:checked out|used by worktree) at ['\"]?([^'\"]+)['\"]?/", $error, $matches)) {
            return rtrim($matches[1]);
        }

        return null;
    }

    /**
     * Get the stored worktree preference ('always', or null if not set).
     */
    protected function getWorktreePreference(): ?string
    {
        if (Storage::exists('worktree-preference.txt')) {
            return trim(Storage::get('worktree-preference.txt'));
        }

        return null;
    }
}
