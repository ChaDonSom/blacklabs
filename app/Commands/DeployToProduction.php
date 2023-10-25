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
    protected $signature = 'deploy-to-production {branch? : The branch to deploy (will ask if not provided)}';

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
        $this->warn("[WARNING] Only run this command if you know what you're doing.");
        $inputName = $this->ask("Please type the name of the production branch to continue.");
        if ($inputName !== $production) {
            $this->error("You didn't type the name of the production branch correctly. Aborting.");
            return;
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

        $this->info("Merging {$branch} into production.");
        $this->runProcess("git merge origin/{$branch}");

        $this->info("Pushing production branch.");
        $this->runProcess("git push");

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
        
        // Get the version from the branch, compare it against the previous version to get the type of version bump
        $versionFromBranch = Str::of($branch)->after('release/')->before('/');
        Log::debug("Version from branch: $versionFromBranch");
        Log::debug("Current version: $currentVersion");

        $versionBump = 'patch';
        if ($this->isVersionNumber($versionFromBranch)) {
            for ($i = 0; $i < strlen($versionFromBranch); $i++) {
                if ($versionFromBranch[$i] !== $currentVersion[$i]) {
                    $versionBump = $i === 1 ? 'major' : ($i === 3 ? 'minor' : 'patch');
                    break;
                }
            }
        }

        // Tag it and push
        $this->info("Running $versionBump from $currentVersion.");
        Log::debug("Running $versionBump from $currentVersion.");
        $this->runProcess("npm version {$versionBump}");
        $this->runProcess("git push");
        $this->runProcess("git push --tags");

        $this->info("New version: " . $this->runProcess("git describe --tags --abbrev=0"));
        $this->info("Done!");
    }

    public function isVersionNumber($string) {
        return preg_match('/^v?\d+\.\d+\.\d+$/', $string);
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
