<?php

namespace App\Commands;

use App\Services\RunsProcesses;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;
use Illuminate\Support\Str;

class DeployToProduction extends Command
{
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
        if (!$this->option('force')) {
            $production = 'forge-production';
            $this->warn("[WARNING] Only run this command if you know what you're doing.");
            $inputName = $this->ask("Please type the name of the production branch to continue.");
            if ($inputName !== $production) {
                $this->error("You didn't type the name of the production branch correctly. Aborting.");
                return;
            }
        }

        $branch = $this->argument('branch');
        if (!$branch) {
            $branchesToChooseFrom = explode("\n", $this->runProcess('git branch --list "release/*" --format="%(refname:short)"'));
            $branch = $this->anticipate('Which branch should we deploy?', $branchesToChooseFrom);
        }

        $this->info("Deploying {$branch} to production.");

        $this->info("Checking out production branch.");
        $this->runProcess("git checkout {$production}");

        // Get current version for later
        $currentVersion = 'v' . $this->runProcess("node -p \"require('./package.json').version\"");

        $this->info("Pulling latest production branch.");
        $this->runProcess("git pull");

        $this->info("Merging {$branch} into production."); // Merging release/v0.31.4-0/1537 into production.
        $this->runProcess("git merge origin/{$branch}");

        // Get the version from the branch, compare it against the previous version to get the type of version bump
        $versionFromBranch = Str::of($branch)->after('release/')->before('/');
        Log::debug("Version from branch: $versionFromBranch");
        Log::debug("Current version: $currentVersion");

        $versionBump = '?';
        if ($this->isVersionNumber($versionFromBranch)) {
            // Parse semantic versions properly
            $currentParts = $this->parseVersion($currentVersion);
            $newParts = $this->parseVersion($versionFromBranch);
            
            Log::debug("Current version parts: " . json_encode($currentParts));
            Log::debug("New version parts: " . json_encode($newParts));
            
            if ($currentParts && $newParts) {
                if ($newParts['major'] > $currentParts['major']) {
                    $versionBump = 'major';
                } elseif ($newParts['major'] === $currentParts['major'] && $newParts['minor'] > $currentParts['minor']) {
                    $versionBump = 'minor';
                } elseif ($newParts['major'] === $currentParts['major'] && $newParts['minor'] === $currentParts['minor'] && $newParts['patch'] > $currentParts['patch']) {
                    $versionBump = 'patch';
                }
                Log::debug("Version bump: $versionBump");
            }
        } else if ($this->isIssueBranch($versionFromBranch)) {
            Log::debug("Branch is an issue branch.");
            $versionBump = 'patch';
        }

        // Tag it and push
        $this->info("Running $versionBump from $currentVersion.");
        Log::debug("Running $versionBump from $currentVersion.");
        $this->runProcess("npm version {$versionBump}");

        $this->info("Pushing production branch.");
        $this->runProcess("git push");
        $this->runProcess("git push --tags");

        $this->info("Checking out dev branch.");
        $this->runProcess("git checkout dev");

        $this->info("Pulling latest dev branch.");
        $this->runProcess("git pull");

        $this->info("Merging production into dev.");
        $this->runProcess("git merge {$production}");

        $this->info("Pushing dev branch.");
        $this->runProcess("git push");

        $this->info("Deleting {$branch}.");
        $this->runProcess("git branch -D {$branch}");
        $this->runProcess("git push origin --delete {$branch}");

        $this->info("New version: " . $this->runProcess("git describe --tags --abbrev=0"));
        $this->info("Done!");
    }

    public function isVersionNumber($string)
    {
        return preg_match('/^v?\d+\.\d+\.\d+(-\d+)?$/', $string);
    }

    /**
     * Parse a semantic version string into its components.
     * 
     * @param string $version Version string like "v1.2.3" or "v1.2.3-0"
     * @return array|null Array with 'major', 'minor', 'patch' keys or null if invalid
     */
    private function parseVersion(string $version): ?array
    {
        // Remove 'v' prefix if present
        $version = ltrim($version, 'v');
        
        // Match semantic version pattern
        if (preg_match('/^(\d+)\.(\d+)\.(\d+)(?:-\d+)?$/', $version, $matches)) {
            return [
                'major' => (int)$matches[1],
                'minor' => (int)$matches[2],
                'patch' => (int)$matches[3],
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
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
