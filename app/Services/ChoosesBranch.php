<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use RuntimeException;

use function Laravel\Prompts\search;

trait ChoosesBranch {
    use RunsProcesses;

    /**
     * Choose a branch from the list of branches.
     * @param null|string $overrideBranch: If not null, this branch will be chosen without prompting.
     * @param null|string $message: The message to display when prompting for a branch.
     * @param null|bool $excludeCurrentBranch: If true, the current branch will not be included in the list of branches to choose from.
     * @return int|string 
     * @throws Exception 
     * @throws RuntimeException 
     */
    public function chooseBranch(?string $overrideBranch, ?string $message, ?bool $excludeCurrentBranch = false) {
        // The current branch
        $currentBranch = $this->runProcess('git rev-parse --abbrev-ref HEAD');

        // Get the most recent branches using git in the current folder
        $branches = collect(explode("\n", shell_exec('git branch -r --sort=-committerdate')))
            ->map(fn ($branch) => trim(str_replace('origin/', '', $branch)))
            ->filter(fn ($branch) => $excludeCurrentBranch ? $branch != $currentBranch : true)
            ->filter(fn ($branch) => $branch != 'HEAD' && $branch != 'master')
            ->values();

        return $overrideBranch ?? search(
            label: $message ?? 'What branch would you like to choose?',
            options: fn (string $value) => strlen($value) > 0
                ? $branches->filter(fn ($branch) => str_contains($branch, $value))->values()->toArray()
                : $branches->toArray(),
            scroll: 10,
        );
    }
}