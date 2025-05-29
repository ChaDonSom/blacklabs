<?php

namespace App\Commands;

use App\Services\RunsProcesses;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Process;
use LaravelZero\Framework\Commands\Command;

class RecentDiffsForFile extends Command
{
    use RunsProcesses;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'recent-diffs-for-file {file?} {search?}
        {--l|line= : Check for diffs around a specific line number}
        {--r|range=3 : Number of lines to show before and after the line number in the diff output}
    ';
    protected $file = null;
    protected $search = null;

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Find any branches that have been recently updated with changes to a specific file.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        /**
         * Something like this:
         * git for-each-ref --sort=-committerdate --format='%(refname:short)' refs/heads/ | head -n 5 | while read branch; do
            echo "== $branch ==";
            git diff HEAD "$branch" -- path/to/file.py | grep -C3 'some_function_or_line'
            done
         */

        $file = $this->argument('file');
        if (!$file) {
            $file = $this->ask('Please provide the file path to check for recent diffs');
        }
        $this->file = $file;
        $this->info("Checking recent diffs for file: $file");

        $search = $this->argument('search');
        $this->search = $search;
        $searchString = $search ? " | grep -C3 '$search'" : '';
        if ($search) {
            $this->info("Searching for: $search");
        }

        // First, let's check if we can fetch and see remote branches
        $this->info("Fetching latest changes from remote...");
        $this->runProcess("git fetch --all");

        // Check what remote branches we have
        $availableBranches = $this->runProcess(
            "git for-each-ref --sort=-committerdate --format='%(refname:short)' refs/remotes/origin/ | head -n 50"
        );
        if (empty($availableBranches)) {
            $this->error("No remote branches found. Please ensure you have fetched the latest changes.");
            return;
        } else {
            $numBranches = count(explode("\n", $availableBranches));
            $this->info("Found $numBranches remote branches");
        }

        // Now run the main command with better error handling
        $this->info("Checking for diffs...");
        foreach (explode("\n", $availableBranches) as $branch) {
            $branch = trim($branch);
            if (empty($branch)) {
                continue;
            }
            try {
                $diffs = $this->runProcess("git diff HEAD $branch -- $file$searchString");
                if (!empty($diffs) && $this->option('line')) {
                    // Default to 3 for the range if not specified
                    $range = $this->option('range') ?: 3;

                    // Check for diffs around the specified line
                    $line = $this->option('line');
                    try {
                        $diffs = $this->runProcess("git diff HEAD $branch -- $file");
                        $diffs = collect(explode("\n", $diffs))
                            ->filter(function ($lineContent, $index) use ($line, $range) {
                                return ($index >= $line - $range && $index <= $line + $range);
                            })
                            ->implode("\n");
                    } catch (\Exception $e) {
                        $this->error("Error fetching diffs around line $line: " . $e->getMessage());
                        $this->error($e);
                        continue;
                    }
                }

                if (!empty($diffs)) {
                    $this->line("Differences found in branch '$branch'");

                    if ($this->option('line')) {
                        $this->line("Around line {$this->option('line')}:");
                        $this->line($diffs);
                    }
                } else {
                    // $this->info("No changes found in branch: $branch for file: $file");
                }
            } catch (\Exception $e) {
                $this->error("Error checking branch '$branch': " . $e->getMessage());
            }
        }
    }

    /**
     * Define the command's schedule.
     */
    public function schedule(Schedule $schedule): void
    {
        // $schedule->command(static::class)->everyMinute();
    }
}
