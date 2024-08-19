<?php

namespace App\Services;

use Illuminate\Support\Str;

trait ManagesCleanupBranches
{
    use RunsProcesses;

    public $silent = false;

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

    /**
     * Get the list of files that will have merge conflicts with the next active cleanup branch, as an array of key-
     * value pairs, where the key is the file name, and the value is the file's contents having the merge conflicts.
     */
    public function getFilesThatWillConflictWithBranch($branch): array
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

        // Get each file's contents.
        $conflictingFiles = collect(explode("\n", $conflictingFiles))->mapWithKeys(function ($file) {
            return [$file => $this->runProcess("cat {$file}")];
        });

        $this->info("Resetting the current branch.");
        $this->runProcess("git reset --hard HEAD");

        return $conflictingFiles->toArray();
    }

    public function getFilesThatAreChangedByBothBranches($branch = null): array
    {
        // We do a diff from the common ancestor of both branches, to the head of both brenches. We compare the
        // files that are different between the two branches, and return the list of files that are different.

        if (!$branch) {
            $branch = $this->findCurrentCleanupBranch();
        }

        $this->info("Getting the common ancestor of the current branch and the cleanup branch.");
        $commonAncestor = $this->getCommonAncestor($branch);

        $this->info("Getting the files that have been changed by the current branch.");
        $currentBranchFiles = $this->getFilesThatAreChangedBetween($commonAncestor, 'HEAD');

        $this->info("Getting the files that have been changed by the cleanup branch.");
        $cleanupBranchFiles = $this->getFilesThatAreChangedBetween($commonAncestor, $branch);

        $files = collect(array_intersect($currentBranchFiles, $cleanupBranchFiles))->unique();

        $this->info("The following files have been changed by both the current branch and the cleanup branch:");
        $this->info(collect($files)->join("\n"));

        return $files->toArray();
    }

    public function getFilesThatAreChangedByCleanupBranch(): array
    {
        $branch = $this->findCurrentCleanupBranch();

        $commonAncestor = $this->getCommonAncestor($branch);

        return $this->getFilesThatAreChangedBetween($commonAncestor, $branch);
    }

    public function getFilesThatAreChangedBetween($start, $end): array
    {
        $files = $this->runProcess("git diff --name-only $start $end");

        if (!$this->silent) $this->info($files);

        return explode("\n", $files);
    }

    public function getCommonAncestor($branch): string
    {
        return $this->runProcess("git merge-base HEAD $branch");
    }

    /**
     * @param mixed $branch
     * @param mixed $files
     * @return void
     * @throws Exception
     */
    public function copyFiles($branch, $files): void
    {
        foreach ($files as $file) {
            $this->info("Merging {$file}.");

            // Checkout the file from the cleanup branch.
            $this->runProcess("git checkout {$branch} -- {$file}");

            // Add the file to the staging area.
            $this->runProcess("git add {$file}");
        }
    }

    public function applyMergeConflictFiles($files): void
    {
        foreach ($files as $file => $contents) {
            $this->info("Applying {$file}.");
            file_put_contents($file, $contents);
            $this->runProcess("git add {$file}");
        }
    }
}
