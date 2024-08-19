<?php

namespace App\Commands;

use App\Services\FindsReferencesBetweenFiles;
use App\Services\ManagesCleanupBranches;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class CleanupCheckFileForReferences extends Command
{
    use ManagesCleanupBranches;
    use FindsReferencesBetweenFiles;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'cleanup:check-file {file}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Check the given file for references to any files that have been changed in the next active cleanup branch.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $file = $this->argument('file');

        $this->info("Finding the current cleanup branch.");
        $branch = $this->findCurrentCleanupBranch();

        $this->info("The next active cleanup branch is: {$branch}");

        $this->info("Getting the list of files that have been changed in the next active cleanup branch.");
        $this->silent = true;
        $cleanupBranchFiles = $this->getFilesThatAreChangedByCleanupBranch();

        $this->info("Checking for references to the cleanup branch's files from the given file.");
        $references = $this->getReferencesToFiles($file, $cleanupBranchFiles, true);

        if ($references) {
            $this->info("The given file references the following files:");
            $this->info(collect($references)->join("\n"));
        } else {
            $this->info("No references found.");
        }
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
