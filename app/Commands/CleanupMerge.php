<?php

namespace App\Commands;

use App\Services\ManagesCleanupBranches;
use Exception;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

/** @package App\Commands */
class CleanupMerge extends Command
{
    use ManagesCleanupBranches;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'cleanup:merge {-k|--keep : Keep non-conflicting files}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Merges conflicts with the next active cleanup branch.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $branch = $this->findCurrentCleanupBranch();
        $conflictingFiles = $this->getFilesThatWillConflictWithBranch($branch);
        print "\n" . collect($conflictingFiles)->keys()->implode("\n") . "\n";
        $allFiles = $this->getFilesThatAreChangedByBothBranches($branch);
        $nonConflictingFiles = array_diff($allFiles, array_keys($conflictingFiles));
        print "\n" . collect($nonConflictingFiles)->implode("\n") . "\n";

        // For the conflicting files, we should simply apply the file contents we got.
        foreach ($conflictingFiles as $file => $contents) {
            $this->info("Applying {$file}.");
            file_put_contents($file, $contents);
            $this->runProcess("git add {$file}");
        }

        // For the non-conflicting files, we should merge them.
        $this->mergeFiles($branch, $nonConflictingFiles);
    }

    /**
     * @param mixed $branch
     * @param mixed $files
     * @return void
     * @throws Exception
     */
    public function mergeFiles($branch, $files): void
    {
        foreach ($files as $file) {
            $this->info("Merging {$file}.");

            // Checkout the file from the cleanup branch.
            $this->runProcess("git checkout {$branch} -- {$file}");

            // Add the file to the staging area.
            $this->runProcess("git add {$file}");
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
