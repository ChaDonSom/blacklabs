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

    public function getFilesThatWillConflictWithBranch($branch, $reset = true, $keep = false)
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
        } else if ($conflictingFiles) {
            if ($keep) {
                $this->info("Keeping non-conflicting files.");
                // Do nothing
            } else {
                $this->info("Removing non-conflicting files.");

                // Get the list of non-conflicting files
                $nonConflictingFiles = $this->runProcess("git status --porcelain | grep -v '^UU' | awk '{print $2}'");
                $this->info($nonConflictingFiles);
                $nonConflictingFiles = collect(explode("\n", $nonConflictingFiles))->map(function ($file) {
                    return trim($file);
                })->filter(function ($file) {
                    return $file;
                });

                $this->info("Removing {$nonConflictingFiles->count()} non-conflicting files.");

                // Unstage deletions and discard changes for modified files
                $nonConflictingFiles->each(function ($file) {
                    try {
                        if (!empty($file)) {
                            $this->runProcess('git restore --staged ' . $file);
                            $this->runProcess('git checkout -- ' . $file);
                        }
                    } catch (\Exception $e) {
                        $this->error("Failed to process file: $file");
                        $this->error($e->getMessage());
                    }
                });
            }
        }

        return $conflictingFiles;
    }
}
