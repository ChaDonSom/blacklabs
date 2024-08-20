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

    /**
     * Get the list of files that will have merge conflicts with the next active cleanup branch, as an array of key-
     * value pairs, where the key is the file name, and the value is the file's contents having the merge conflicts.
     */
    public function getFilesThatWillConflictWithBranch($branch): array
    {
        $this->runProcess("git fetch origin $branch");

        try {
            $this->runProcess("git merge $branch --no-commit --no-ff");
        } catch (\Exception $e) {
        }

        $conflictingFiles = $this->runProcess("git diff --name-only --diff-filter=U");

        // Get each file's contents.
        $conflictingFiles = collect(explode("\n", $conflictingFiles))->mapWithKeys(function ($file) {
            return [$file => $this->runProcess("cat {$file}")];
        });

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

        $commonAncestor = $this->getCommonAncestor($branch);

        $currentBranchFiles = $this->getFilesThatAreChangedBetween($commonAncestor, 'HEAD');

        $cleanupBranchFiles = $this->getFilesThatAreChangedBetween($commonAncestor, $branch);

        $files = collect(array_intersect($currentBranchFiles, $cleanupBranchFiles))->unique();

        return $files->toArray();
    }

    public function getFilesThatAreChangedByCleanupBranch(): array
    {
        $branch = $this->findCurrentCleanupBranch();

        $commonAncestor = $this->getCommonAncestor($branch);

        return $this->getFilesThatAreChangedBetween($commonAncestor, $branch);
    }

    public function getFilesThatAreChangedByCurrentBranch(): array
    {
        $branch = $this->findCurrentCleanupBranch();

        $commonAncestor = $this->getCommonAncestor($branch);

        return $this->getFilesThatAreChangedBetween($commonAncestor, 'HEAD');
    }

    public function getFilesThatAreChangedBetween($start, $end): array
    {
        $files = $this->runProcess("git diff --name-only $start $end");

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
            try {
                $this->runProcess("git checkout {$branch} -- {$file}");
                $this->runProcess("git add {$file}");
            } catch (\Exception $e) {
                $this->error("Failed to find file in git: {$file}");
            }
        }
    }

    public function applyMergeConflictFiles($files): void
    {
        foreach (collect($files)->filter() as $file => $contents) {
            file_put_contents($file, $contents);
            $this->runProcess("git add {$file}");
        }
    }
}
