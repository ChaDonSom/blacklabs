<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

trait ManagesGitWorktrees
{
    /**
     * Try to check out the given branch. If the branch is already checked out in
     * another worktree, offer to switch to that worktree (interactive) or
     * automatically switch if the worktree preference is 'always'.
     *
     * Uses `git worktree list --porcelain` (a stable, machine-readable interface)
     * to detect conflicts before attempting checkout, avoiding reliance on git's
     * human-readable error messages which can change between git versions.
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
        $worktreePath = $this->findWorktreeForBranch($branch);

        if ($worktreePath !== null && $this->shouldSwitchToWorktree($branch, $worktreePath)) {
            if (! chdir($worktreePath)) {
                $this->error("Failed to switch to git worktree at '{$worktreePath}'.");
                throw new \Exception("Branch '{$branch}' is already checked out in worktree at '{$worktreePath}' but could not switch to that directory.");
            }

            $this->info("Switched to worktree at {$worktreePath}.");

            return true;
        }

        if ($worktreePath !== null) {
            // User declined to switch; throw without attempting the checkout (which would fail anyway)
            throw new \Exception("Branch '{$branch}' is already checked out in another worktree at '{$worktreePath}'.");
        }

        $this->runProcess('git checkout ' . escapeshellarg($branch));

        return false;
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
     * Find the worktree path that has the given branch checked out, excluding the
     * current worktree. Uses `git worktree list --porcelain` which is a stable,
     * machine-readable interface available since git 2.7.0 (2016).
     *
     * Returns null if the branch is not checked out in any other worktree.
     */
    protected function findWorktreeForBranch(string $branch): ?string
    {
        try {
            $output = $this->runProcess('git worktree list --porcelain');
            $currentWorktree = $this->runProcess('git rev-parse --show-toplevel');
        } catch (\Exception $e) {
            return null;
        }

        foreach (preg_split('/\R\R/', trim($output)) as $block) {
            $worktreePath = null;
            $hasBranch = false;

            foreach (preg_split('/\R/', trim($block)) as $line) {
                $line = trim($line);
                if (str_starts_with($line, 'worktree ')) {
                    $worktreePath = substr($line, 9);
                } elseif ($line === "branch refs/heads/{$branch}") {
                    $hasBranch = true;
                }
            }

            if ($worktreePath && $hasBranch && $worktreePath !== $currentWorktree) {
                return $worktreePath;
            }
        }

        return null;
    }

    /**
     * Delete a local branch, gracefully handling the case where the branch is checked out
     * in a git worktree.
     *
     * - If the branch is in a **clean** worktree, removes the worktree directory and then
     *   deletes the branch as normal.
     * - If the branch is in a **dirty** worktree (uncommitted changes), emits a warning and
     *   skips local deletion, returning false so the caller can decide what to do next.
     * - If the branch is not in any worktree, deletes it with `git branch -D`.
     *
     * @return bool True if the branch was deleted locally, false if it was skipped.
     */
    protected function deleteLocalBranch(string $branch): bool
    {
        $worktreePath = $this->findWorktreeForBranch($branch);

        if ($worktreePath !== null) {
            try {
                $status = $this->runProcess('git -C ' . escapeshellarg($worktreePath) . ' status --porcelain');
            } catch (\Exception $e) {
                $this->warn("Could not check worktree status at '{$worktreePath}': {$e->getMessage()}. Skipping local branch deletion.");

                return false;
            }

            if (! empty($status)) {
                $this->warn("Branch '{$branch}' is checked out at '{$worktreePath}' with unsaved changes. Leaving it here for you.");

                return false;
            }

            $this->runProcess('git worktree remove ' . escapeshellarg($worktreePath));
        }

        $this->runProcess('git branch -D ' . escapeshellarg($branch));

        return true;
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
