<?php

namespace App\Commands;

use App\Services\FindsReferencesBetweenFiles;
use App\Services\ManagesCleanupBranches;
use App\Services\MergesBranches;
use Exception;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

/** @package App\Commands */
class CleanupMerge extends Command
{
    use ManagesCleanupBranches;
    use FindsReferencesBetweenFiles;
    use MergesBranches;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'cleanup:merge';

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

        // The better outline might be a little different.

        /**
         *
         * We need to merge the current branch into the cleanup branch first, then we can merge the cleanup branch
         * into the current branch. This will allow us to resolve conflicts in the cleanup branch first.
         *
         * 1. Find the current cleanup branch.
         * 2. Checkout the current cleanup branch and make a copy of it.
         * 3. Merge the current branch into the cleanup branch.
         *    a. Pause execution if there are conflicts and wait for the user to resolve them.
         * 4. Checkout back to the current branch.
         * 5. Merge files from the copy of the cleanup branch.
         * 6. Delete the copy of the cleanup branch.
         */

        // 1. Find the current cleanup branch.
        $branch = $this->findCurrentCleanupBranch();

        // 2. Checkout the current cleanup branch and make a copy of it.
        $this->info("Checking out the cleanup branch.");
        // Store the current branch to use in step 3.
        $currentBranch = $this->runProcess("git rev-parse --abbrev-ref HEAD");
        $this->runProcess("git checkout $branch");
        $tempBranch = "temp/cleanup-" . explode('-', $branch)[0];
        $this->runProcess("git checkout -b $tempBranch");

        // 3. Merge the current branch into the cleanup branch.
        $this->info("Merging the current branch into the cleanup branch.");
        $this->mergeBranches(branches: [$currentBranch], remote: false);

        // 4. Checkout back to the current branch.
        $this->info("Checking out the current branch.");
        $this->runProcess("git checkout $currentBranch");

        // 5. Merge files from the copy of the cleanup branch.
        $this->info("Merging the cleanup branch into the current branch.");

        $this->info("Merging files.");
        $allFiles = $this->getFilesThatAreChangedByBothBranches($branch);
        // For the non-conflicting files, we should merge them.
        $this->copyFiles($tempBranch, $allFiles);

        $this->info("Merging referenced files.");
        // We should also find the files that the current branch has modified, find the files they reference of the
        // ones the cleanup branch has modified, and copy those as well.
        $currentFiles = $this->getFilesThatAreChangedByCurrentBranch();
        $cleanupFiles = $this->getFilesThatAreChangedByCleanupBranch();
        $references = collect($currentFiles)->map(function ($file) use ($cleanupFiles) {
            $refs = $this->getReferencesToFiles($file, $cleanupFiles);
            if (count($refs)) $this->info("References from $file: \n" . collect($refs)->join('\n'));
            return $refs;
        })->flatten()->unique()->toArray();

        if ($references) {
            $this->copyFiles($branch, $references);
        }

        // 6. Delete the copy of the cleanup branch.
        $this->info("Deleting the copy of the cleanup branch.");
        $this->runProcess("git branch -D $tempBranch");

        $this->info("Merging complete.");
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
