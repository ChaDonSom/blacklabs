<?php

namespace App\Services;

use Illuminate\Support\Str;

trait ManagesCleanupBranches
{
    use RunsProcesses;

    public function findCurrentCleanupBranch()
    {
        $output = $this->runProcess("git branch | grep \"big-cleanup\"");

        if (!$output || empty($output)) {
            $this->error("No cleanup branches found.");
            return;
        }

        $output = collect(explode("\n", $output))->map(function ($branch) {
            return (object) [
                'branch' => Str::of($branch)->whenContains('*', fn($str) => $str->replace('*', ''))->trim(),
                'sequence' => Str::of($branch)->after('big-cleanup-')->before('-'),
            ];
        })->sortBy('sequence')->first()->branch;

        return $output;
    }

    public function getFilesThatWillConflictWithBranch($branch, $reset = true): array
    {
        $this->info("Getting latest updates for the next active cleanup branch.");
        $this->runProcess("git fetch origin $branch");

        $this->info("Merging the next active cleanup branch into the current branch.");
        try {
            $this->runProcess("git merge $branch --no-commit --no-ff");
        } catch (\Exception $e) {
        }

        $this->info("Checking for merge conflicts.");
        $conflictingFiles = $this->runProcess("git diff --name-only --diff-filter=U");

        if ($conflictingFiles) {
            $this->info("The following files will have merge conflicts with the next active cleanup branch:");
            $this->info($conflictingFiles);
        } else {
            $this->info("No merge conflicts found.");
        }


        if ($reset) {
            $this->info("Resetting the current branch.");
            $this->runProcess("git reset --hard HEAD");
        }

        return explode("\n", $conflictingFiles);
    }

    public function getFilesThatAreChangedByBothBranches($branch): array
    {
        // We do a diff from the common ancestor of both branches, to the head of both brenches. We compare the
        // files that are different between the two branches, and return the list of files that are different.

        $this->info("Getting the common ancestor of the current branch and the cleanup branch.");
        $commonAncestor = $this->runProcess("git merge-base HEAD $branch");

        $this->info("Getting the files that have been changed by the current branch.");
        $currentBranchFiles = $this->runProcess("git diff --name-only $commonAncestor HEAD");

        $this->info("Getting the files that have been changed by the cleanup branch.");
        $cleanupBranchFiles = $this->runProcess("git diff --name-only $commonAncestor $branch");

        $currentBranchFiles = explode("\n", $currentBranchFiles);
        $cleanupBranchFiles = explode("\n", $cleanupBranchFiles);

        $files = array_intersect($currentBranchFiles, $cleanupBranchFiles);

        $this->info("The following files have been changed by both the current branch and the cleanup branch:");
        $this->info(collect($files)->join("\n"));

        return $files;
    }
}
