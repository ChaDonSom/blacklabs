<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

class DeployToProduction extends Command
{
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

        $this->info("Done!");
    }

    public function runProcess($command) {
        $result = Process::run($command);
        if (!$result->successful()) {
            if ($result->errorOutput() && $result->output()) {
                throw new \Exception($result->errorOutput() . "\n" . $result->output());
            } else {
                throw new \Exception($result->errorOutput() ?: $result->output());
            }
        }
        return $result->output();
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
