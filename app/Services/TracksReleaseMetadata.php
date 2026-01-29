<?php

namespace App\Services;

use Illuminate\Support\Str;

trait TracksReleaseMetadata
{
    private const METADATA_FILE = '.blacklabs/releases.json';
    
    /**
     * Store which issues are included in a release branch.
     * This creates/updates a JSON file in the repo that travels with git.
     */
    public function storeReleaseIssues(string $branchName, array $issues): void
    {
        $metadata = $this->loadMetadataFile();
        
        $normalizedBranch = str_replace('origin/', '', $branchName);
        
        $metadata[$normalizedBranch] = [
            'issues' => $issues,
            'version' => $this->extractVersionFromBranch($branchName),
            'created_at' => $metadata[$normalizedBranch]['created_at'] ?? now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'created_by' => $this->getGitUser(),
        ];
        
        $this->saveMetadataFile($metadata);
    }
    
    /**
     * Get issues for a release branch from the metadata file.
     * Falls back to parsing branch name for backwards compatibility.
     */
    public function getReleaseIssues(string $branchName): array
    {
        $metadata = $this->loadMetadataFile();
        $normalizedBranch = str_replace('origin/', '', $branchName);
        
        // Try exact match
        if (isset($metadata[$normalizedBranch]['issues'])) {
            return $metadata[$normalizedBranch]['issues'];
        }
        
        // Fallback: parse from branch name (legacy branches)
        $this->warn("No metadata found for {$normalizedBranch}, parsing from branch name");
        return $this->parseIssuesFromBranchName($branchName);
    }
    
    /**
     * Get all release metadata (useful for reporting).
     */
    public function getAllReleaseMetadata(): array
    {
        return $this->loadMetadataFile();
    }
    
    /**
     * Load the metadata file from the repository.
     */
    private function loadMetadataFile(): array
    {
        $path = getcwd() . '/' . self::METADATA_FILE;
        
        if (!file_exists($path)) {
            $this->initializeMetadataFile();
            return [];
        }
        
        $contents = file_get_contents($path);
        return json_decode($contents, true) ?? [];
    }
    
    /**
     * Save metadata file and stage it for commit.
     */
    private function saveMetadataFile(array $metadata): void
    {
        $path = getcwd() . '/' . self::METADATA_FILE;
        
        // Sort by branch name for consistent diffs
        ksort($metadata);
        
        $json = json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($path, $json . "\n");
        
        // Stage the file automatically so it gets committed with the release
        try {
            $this->runProcess('git add ' . self::METADATA_FILE);
        } catch (\Exception $e) {
            $this->warn("Could not stage metadata file: " . $e->getMessage());
        }
    }
    
    /**
     * Initialize metadata file structure.
     */
    private function initializeMetadataFile(): void
    {
        $dir = dirname(getcwd() . '/' . self::METADATA_FILE);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $this->saveMetadataFile([]);
    }
    
    /**
     * Extract version from branch name.
     */
    private function extractVersionFromBranch(string $branchName): string
    {
        // Match release/v1.2.3-4/... or release/v1.2.3-4
        if (preg_match('#release/(v?[\d\.]+(?:-\d+)?)#', $branchName, $matches)) {
            return $matches[1];
        }
        return 'unknown';
    }
    
    /**
     * Get current git user for audit trail.
     */
    private function getGitUser(): string
    {
        try {
            $name = trim($this->runProcess('git config user.name'));
            $email = trim($this->runProcess('git config user.email'));
            return "{$name} <{$email}>";
        } catch (\Exception $e) {
            return 'unknown';
        }
    }
    
    /**
     * Parse issues from legacy branch names (backwards compatibility).
     * Original format: release/v1.2.3/123-456-789
     */
    private function parseIssuesFromBranchName(string $branchName): array
    {
        $issuesPart = Str::of($branchName)
            ->after('release/')
            ->after('/')
            ->toString();
        
        if (empty($issuesPart)) {
            return [];
        }
        
        return array_filter(explode('-', $issuesPart));
    }
}
