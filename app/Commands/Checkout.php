<?php

namespace App\Commands;

use App\Services\ChoosesBranch;
use App\Services\RunsProcesses;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Collection;
use LaravelZero\Framework\Commands\Command;

class Checkout extends Command
{
    use ChoosesBranch;
    use RunsProcesses;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'checkout {branch? : The branch to check out to} {--i|install : Install dependencies after checking out}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Checks out to the specified branch, but rolls back migrations in the current branch first if necessary, and then runs migrations in the new branch if necessary.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Abort if working directory is dirty
        $dirty = $this->runProcess("git status --porcelain");
        if ($dirty) {
            $this->error("Working directory is dirty. Please commit or stash your changes before continuing.");
            return 1;
        }

        // If branch is not given, ask for it
        $branch = $this->chooseBranch(
            overrideBranch: $this->argument('branch'),
            message: "Which branch would you like to check out to?",
            excludeCurrentBranch: true,
        );

        $this->info("Checking for migrations in current branch first...");
        $currentBranch = $this->runProcess("git rev-parse --abbrev-ref HEAD");
        
        // Roll back the migrations in this branch first, if there are any
        $migrations = $this->getCurrentBranchMigrations(compareBranch: $branch);
        if (count($migrations)) {
            $this->info("\nRolling back migrations in current branch...");
            $this->checkFoundMigrationsAgainstPretend($migrations, true);
            try {
                $this->runProcess("php artisan migrate:rollback --force");
            } catch (\Exception $e) {
                $this->error("Error rolling back migrations in current branch: {$e->getMessage()}");
                return 1;
            }
        }

        // Check out to the chosen branch
        $this->info("\nChecking out to {$branch}...");
        try {
            $this->runProcess("git checkout {$branch}");
        } catch (\Exception $e) {
            $this->error("Error checking out to {$branch}: {$e->getMessage()}");
            return 1;
        }

        // Run migrations in the new branch if there are any
        $migrations = $this->getCurrentBranchMigrations($currentBranch);
        if (count($migrations)) {
            $this->info("\nRunning migrations in new branch...");
            $this->checkFoundMigrationsAgainstPretend($migrations);
            try {
                $this->runProcess("php artisan migrate --force");
            } catch (\Exception $e) {
                $this->error("Error rolling back migrations in current branch: {$e->getMessage()}");
                return 1;
            }
        }

        // Install dependencies if requested
        if ($this->option('install')) {
            $this->info("Installing dependencies...");
            try {
                $this->runProcess("composer install");
            } catch (\Exception $e) {
                $this->error("Error installing dependencies: {$e->getMessage()}");
                return 1;
            }
            try {
                $this->runProcess("npm install");
            } catch (\Exception $e) {
                $this->error("Error installing dependencies: {$e->getMessage()}");
                return 1;
            }
        }

        $this->info("\nDone!");
    }

    public function getCurrentBranchMigrations(string $compareBranch): Collection {
        // Check for existing migrations that were created in the current branch, as compared to the $compareBranch
        $migrations = $this->runProcess("git diff --name-only {$compareBranch}...HEAD -- database/migrations");

        return collect(array_filter(explode("\n", $migrations), fn($migration) => strlen($migration) > 0));
    }

    public function checkFoundMigrationsAgainstPretend(Collection $migrations, $rollback = false) {
        $trimmedMigrations = collect($migrations)->map(function ($migration) {
            $exploded = explode('/', $migration);
            $result = array_pop($exploded);
            $result = explode('.', $result)[0];
            return $result;
        });

        $this->info("Migrations we found: ");
        $this->info($trimmedMigrations->join("\n"));

        $rollback = $rollback ? ':rollback' : '';
        $pretend = $this->runProcess("php artisan migrate{$rollback} --pretend");

        $hasAllMigrations = $trimmedMigrations->every(fn ($migration) => strpos($pretend, $migration) !== false);
        if (!$hasAllMigrations) {
            $this->info("Missing migrations: ");
            $this->info($trimmedMigrations->filter(fn ($migration) => strpos($pretend, $migration) === false)->join("\n"));
            $this->info("\nMigration command: ");
            $this->warn($pretend);
            $this->error("Not all migrations were found in the migration command. Please check the output above and try again.");
            exit(1);
        }
    }
}
