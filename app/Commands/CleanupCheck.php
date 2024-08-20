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
    protected $description = 'Outputs the list of files that the next active cleanup branch has changed, that the current branch has also changed.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $branch = $this->findCurrentCleanupBranch();

        $this->info("Active cleanup branch: {$branch}");

        $files = $this->getFilesThatAreChangedByBothBranches($branch);

        $this->info("Files: ");
        $this->info(collect($files)->join("\n"));
    }
}
