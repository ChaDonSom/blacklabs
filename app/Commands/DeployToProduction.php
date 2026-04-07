<?php

namespace App\Commands;

use App\Services\ManagesGitWorktrees;
use App\Services\RunsProcesses;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

class DeployToProduction extends Command
{
    use ManagesGitWorktrees;
    use RunsProcesses;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'deploy-to-production {branch? : The branch to deploy (will ask if not provided)} {--f|force : Skip the warning}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Merge the chosen release branch into the production branch, then into dev.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $production = 'forge-production';

        if (! $this->option('force')) {
            $this->warn("[WARNING] Only run this command if you know what you're doing.");
            $inputName = $this->ask('Please type the name of the production branch to continue.');
            if ($inputName !== $production) {
                $this->error("You didn't type the name of the production branch correctly. Aborting.");

                return 1;
            }
        }

        $branch = $this->argument('branch');
        if (! $branch) {
            $branchesOutput = $this->runProcess('git branch --list "release/*" --format="%(refname:short)"');
            $branchesToChooseFrom = array_values(array_filter(explode("\n", $branchesOutput), fn ($s) => $s !== ''));

            if (empty($branchesToChooseFrom)) {
                $this->error('No release branches found. Aborting.');

                return 1;
            }

            $branch = $this->anticipate('Which branch should we deploy?', $branchesToChooseFrom);
        }

        if (! str_starts_with($branch, 'release/')) {
            $this->error('Only release branches can be deployed to production. Aborting.');

            return 1;
        }

        $this->info("Deploying {$branch} to production.");

        $this->info('Checking out production branch.');
        try {
            $this->checkoutBranch($production);
        } catch (\Exception $e) {
            $this->error("Failed to switch to {$production}: {$e->getMessage()}. Aborting.");

            return 1;
        }

        // Get current version for later
        $currentVersion = 'v'.$this->runProcess("node -p \"require('./package.json').version\"");

        $this->info('Pulling latest production branch.');
        $this->runProcess('git pull');

        $this->info("Merging {$branch} into production."); // Merging release/v0.31.4-0/1537 into production.
        $this->runProcess("git merge origin/{$branch}");

        // Get the version from the branch, compare it against the previous version to get the type of version bump
        $versionFromBranch = Str::of($branch)->after('release/')->before('/');
        Log::debug("Version from branch: $versionFromBranch");
        Log::debug("Current version: $currentVersion");

        // Determine the exact target version from the branch name.
        // Strip the prerelease counter (v0.58.0-0 → v0.58.0) to get the final production version.
        $targetVersion = null;
        if ($this->isVersionNumber($versionFromBranch)) {
            // Parse semantic versions properly
            $currentParts = $this->parseVersion($currentVersion);
            $newParts = $this->parseVersion($versionFromBranch);

            Log::debug('Current version parts: '.json_encode($currentParts));
            Log::debug('New version parts: '.json_encode($newParts));

            if (! $currentParts || ! $newParts) {
                Log::error('Failed to parse version numbers for bump calculation.', [
                    'currentVersionRaw' => $currentVersion,
                    'branchVersionRaw' => $versionFromBranch,
                    'currentParts' => $currentParts,
                    'newParts' => $newParts,
                ]);
                $this->error("Unable to determine version bump from {$currentVersion} to {$versionFromBranch}.");

                return 1;
            }

            $isForward =
                $newParts['major'] > $currentParts['major'] ||
                ($newParts['major'] === $currentParts['major'] && $newParts['minor'] > $currentParts['minor']) ||
                ($newParts['major'] === $currentParts['major'] && $newParts['minor'] === $currentParts['minor'] && $newParts['patch'] > $currentParts['patch']);

            if (! $isForward) {
                Log::warning('Unable to determine a forward version bump.', [
                    'currentVersionRaw' => $currentVersion,
                    'branchVersionRaw' => $versionFromBranch,
                    'currentParts' => $currentParts,
                    'newParts' => $newParts,
                ]);
                $this->error("Release version {$versionFromBranch} must be greater than current version {$currentVersion}.");

                return 1;
            }

            // Strip prerelease counter to get the exact final production version.
            $targetVersion = preg_replace('/-\d+$/', '', (string) $versionFromBranch);
            if (! str_starts_with($targetVersion, 'v')) {
                $targetVersion = "v{$targetVersion}";
            }

            Log::debug("Target version: $targetVersion");
        } elseif ($this->isIssueBranch($versionFromBranch)) {
            Log::debug('Branch is an issue branch; defaulting to patch bump.');
            $targetVersion = 'patch';
        } else {
            $this->error("Unable to determine version bump from branch {$branch}.");

            return 1;
        }

        if (! $targetVersion) {
            $this->error("Unable to determine version bump from branch {$branch}.");

            return 1;
        }

        // Tag it and push
        $this->info("Setting version from {$currentVersion} to {$targetVersion}.");
        Log::debug("Setting version from {$currentVersion} to {$targetVersion}.");
        $this->runProcess("npm version {$targetVersion}");

        $this->info('Pushing production branch.');
        $this->runProcess('git push');
        $this->runProcess('git push --tags');

        $this->info('Checking out dev branch.');
        try {
            $this->checkoutBranch('dev');
        } catch (\Exception $e) {
            $this->error("Failed to switch to dev: {$e->getMessage()}. Aborting.");

            return 1;
        }

        $this->info('Pulling latest dev branch.');
        $this->runProcess('git pull');

        $this->info('Merging production into dev.');
        $this->runProcess("git merge {$production}");

        $this->info('Pushing dev branch.');
        $this->runProcess('git push');

        $this->info("Deleting {$branch}.");
        $this->runProcess("git branch -D {$branch}");
        $this->runProcess("git push origin --delete {$branch}");

        $this->info('New version: '.$this->runProcess('git describe --tags --abbrev=0'));
        $this->info('Done!');

        return 0;
    }

    public function isVersionNumber($string)
    {
        return preg_match('/^v?\d+\.\d+\.\d+(-\d+)?$/', $string);
    }

    /**
     * Parse a semantic version string into its components.
     *
     * @param  string  $version  Version string like "v1.2.3" or "v1.2.3-0"
     * @return array|null Array with 'major', 'minor', 'patch' keys or null if invalid
     */
    private function parseVersion(string $version): ?array
    {
        // Remove 'v' prefix if present
        $version = ltrim($version, 'v');

        // Match semantic version pattern
        if (preg_match('/^(\d+)\.(\d+)\.(\d+)(?:-\d+)?$/', $version, $matches)) {
            return [
                'major' => (int) $matches[1],
                'minor' => (int) $matches[2],
                'patch' => (int) $matches[3],
            ];
        }

        return null;
    }

    /**
     * Determine whether the given branch is an issue branch. Issue branches are named {issue number}-{issue title slug}.
     */
    private function isIssueBranch(string $branchName): bool
    {
        return preg_match('/^\d+-/', $branchName);
    }

    /**
     * Define the command's schedule.
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
