<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use function Laravel\Prompts\search;

trait ChoosesBranch {
    use RunsProcesses;

    public function chooseBranch(?string $overrideBranch, ?string $message, ?bool $excludeCurrentBranch = false) {
        // The current branch
        $currentBranch = $this->runProcess('git rev-parse --abbrev-ref HEAD');

        // Get the most recent branches using git in the current folder
        $branches = collect(explode("\n", shell_exec('git branch -r --sort=-committerdate')))
            ->map(fn ($branch) => trim(str_replace('origin/', '', $branch)))
            ->filter(fn ($branch) => $excludeCurrentBranch ? $branch != $currentBranch : true)
            ->filter(fn ($branch) => $branch != 'HEAD' && $branch != 'master')
            ->values();

        Log::debug($branches);

        return $overrideBranch ?? search(
            label: $message ?? 'What branch would you like to choose?',
            options: fn (string $value) => strlen($value) > 0
                ? $branches->filter(fn ($branch) => str_contains($branch, $value))->values()->toArray()
                : $branches->toArray(),
            scroll: 10,
        );
    }
}