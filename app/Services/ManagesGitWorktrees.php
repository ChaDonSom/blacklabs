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
     * failure unless the stored preference is 'always'.
     *
     * @throws \Exception If the checkout fails and no worktree switch occurs.
     */
    public function checkoutBranch(string $branch): void
    {
        try {
            $this->runProcess('git checkout ' . escapeshellarg($branch));
        } catch (\Exception $e) {
            $worktreePath = $this->parseWorktreePath($e->getMessage());

            if ($worktreePath !== null && $this->shouldSwitchToWorktree($branch, $worktreePath)) {
                chdir($worktreePath);
                $this->info("Switched to worktree at {$worktreePath}.");

                return;
            }

            throw $e;
        }
    }

    /**
     * Determine whether to switch to the existing worktree for the given branch.
     */
    protected function shouldSwitchToWorktree(string $branch, string $worktreePath): bool
    {
        if ($this->getWorktreePreference() === 'always') {
            return true;
        }

        if (isset($this->input) && ! $this->input->isInteractive()) {
            return false;
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
     * Parse the worktree path from a git "already checked out at" error message.
     * Git error format: fatal: 'branch-name' is already checked out at '/path/to/worktree'
     */
    protected function parseWorktreePath(string $error): ?string
    {
        if (preg_match("/already checked out at ['\"]?([^'\"]+)['\"]?/", $error, $matches)) {
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
