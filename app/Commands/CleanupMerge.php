<?php

namespace App\Commands;

use App\Services\ManagesCleanupBranches;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

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
        $files = $this->getFilesThatWillConflictWithBranch($branch, false, $this->option('keep'));
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
