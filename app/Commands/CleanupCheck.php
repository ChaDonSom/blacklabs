<?php

namespace App\Commands;

use App\Services\ManagesCleanupBranches;
use App\Services\RunsProcesses;
use LaravelZero\Framework\Commands\Command;

class CleanupCheck extends Command
{
    use RunsProcesses;
    use ManagesCleanupBranches;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'cleanup:check';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Outputs the list of files that will have merge conflicts with the next active cleanup branch.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->info("Checking for merge conflicts with the next active cleanup branch.");

        $this->info("Finding the current cleanup branch.");
        $branch = $this->findCurrentCleanupBranch();

        $this->info("The next active cleanup branch is: {$branch}");

        $this->getFilesThatAreChangedByBothBranches($branch);
        return 1;
    }
}
