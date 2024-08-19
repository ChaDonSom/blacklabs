<?php

namespace App\Commands;

use App\Services\ManagesCleanupBranches;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class CleanupCopyFile extends Command
{
    use ManagesCleanupBranches;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'cleanup:copy-file {file}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Copy the given file\'s changes from the next active cleanup branch.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $file = $this->argument('file');
        $branch = $this->findCurrentCleanupBranch();

        $this->info("Getting the latest updates for the next active cleanup branch.");
        $this->runProcess("git fetch origin $branch");

        $conflictingFiles = $this->getFilesThatWillConflictWithBranch($branch);
        if (array_key_exists($file, $conflictingFiles)) {
            $this->applyMergeConflictFiles($branch, [$conflictingFiles[$file]]);
        } else {
            $this->copyFiles($branch, [$file]);
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
