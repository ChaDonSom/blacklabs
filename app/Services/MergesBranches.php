<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use function Laravel\Prompts\confirm;

trait MergesBranches
{
    /**
     * Merge the given branches into the current branch.
     */
    public function mergeBranches(array $branches, $remote = true)
    {
        foreach ($branches as $branchName) {
            // limit the branch name to keep the output clean. if the string is longer than 20 characters, it will
            // be truncated and an ellipsis will be added.
            $branchNameLimited = Str::limit($branchName, 20, '...');
            $this->info("Merging {$branchNameLimited}...");
            try {
                if ($remote) {
                    $this->runProcess("git merge origin/{$branchName}");
                } else {
                    $this->runProcess("git merge {$branchName}");
                }
            } catch (\Exception $e) {
                // If the merge results in merge conflicts, pause the merge and continue when ready.
                if (str_contains($e->getMessage(), 'merge failed')) {
                    $this->warn("Merge conflict detected with branch {$branchNameLimited}."
                        . " Please resolve manually, then continue when you've committed the merge.");
                    confirm("Ready to continue?");
                } else {
                    Log::error($e->getMessage());
                    return $this->error($e->getMessage());
                }
            }
        }
    }
}
