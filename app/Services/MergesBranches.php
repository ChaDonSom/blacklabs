<?php

namespace App\Services;

use Illuminate\Support\Str;

trait MergesBranches {
    public function mergeBranches(array $branches, string $targetBranch) {
        foreach ($branches as $branchName) {
            // limit the branch name to keep the output clean. if the string is longer than 20 characters, it will 
            // be truncated and an ellipsis will be added.
            $branchNameLimited = Str::limit($branchName, 20, '...');
            $targetBranchNameLimited = Str::limit($targetBranch, 20, '...');
            $this->info("Merging {$branchNameLimited} into {$targetBranchNameLimited}...");
            try {
                $this->runProcess("git merge origin/{$branchName}");
            } catch (\Exception $e) {
                // If the merge results in merge conflicts, pause the merge and continue when ready.
                if (str_contains($e->getMessage(), 'merge failed')) {
                    $this->warn("Merge conflict detected with branch {$branchNameLimited}."
                    . " Please resolve manually, then continue when you've committed the merge.");
                    $this->confirm("Ready to continue?");
                } else {
                    Log::error($e->getMessage());
                    return $this->error($e->getMessage());
                }
            }
        }
    }
}