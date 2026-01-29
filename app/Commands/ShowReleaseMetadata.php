<?php

namespace App\Commands;

use App\Services\RunsProcesses;
use App\Services\TracksReleaseMetadata;
use LaravelZero\Framework\Commands\Command;

class ShowReleaseMetadata extends Command
{
    use RunsProcesses;
    use TracksReleaseMetadata;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'show-release-metadata {branch?}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Show release metadata for all branches or a specific branch';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $branchName = $this->argument('branch');

        if ($branchName) {
            $this->showBranchMetadata($branchName);
        } else {
            $this->showAllMetadata();
        }
    }

    /**
     * Show metadata for a specific branch.
     */
    private function showBranchMetadata(string $branchName): void
    {
        $issues = $this->getReleaseIssues($branchName);
        $metadata = $this->getAllReleaseMetadata();
        $normalizedBranch = str_replace('origin/', '', $branchName);

        if (isset($metadata[$normalizedBranch])) {
            $data = $metadata[$normalizedBranch];
            
            $this->info("Release metadata for: {$normalizedBranch}");
            $this->line('');
            $this->line("Version: {$data['version']}");
            $this->line("Issues: " . implode(', ', $data['issues']));
            $this->line("Created: {$data['created_at']}");
            $this->line("Updated: {$data['updated_at']}");
            $this->line("Created by: {$data['created_by']}");
        } else {
            $this->warn("No metadata found for branch: {$branchName}");
            
            if (!empty($issues)) {
                $this->line("Parsed from branch name: " . implode(', ', $issues));
            }
        }
    }

    /**
     * Show metadata for all release branches.
     */
    private function showAllMetadata(): void
    {
        $metadata = $this->getAllReleaseMetadata();

        if (empty($metadata)) {
            $this->warn('No release metadata found.');
            $this->line('Release metadata is stored when creating release branches.');
            return;
        }

        $rows = collect($metadata)->map(function ($data, $branch) {
            return [
                'branch' => $branch,
                'version' => $data['version'] ?? 'unknown',
                'issues' => implode(', ', $data['issues'] ?? []),
                'updated' => $data['updated_at'] ?? 'unknown',
            ];
        })->values()->all();

        $this->table(['Branch', 'Version', 'Issues', 'Last Updated'], $rows);
    }
}
