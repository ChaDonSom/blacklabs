<?php

namespace App\Commands;

use App\Services\FindsIssueBranches;
use App\Services\GetsConsoleSites;
use App\Services\MergesBranches;
use App\Services\RunsProcesses;
use App\Services\UsesForgeHttp;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

class AddOrRemoveIssueFromSite extends Command
{
    use GetsConsoleSites;
    use UsesForgeHttp;
    use RunsProcesses;
    use FindsIssueBranches;
    use MergesBranches;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'site {add-issues : add-issues | remove-issues} {site} {issues}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Adds or removes the given issues from the given site.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $site = $this->argument('site');
        Log::debug('Site:', [$site]);
        $issues = $this->argument('issues');
        $issues = explode(',', $issues);

        $isAdding = $this->argument('add-issues') === 'add-issues';
        if (!$isAdding && $this->argument('add-issues') !== 'remove-issues') {
            $this->error('You must specify whether you are adding or removing issues.');
            return 1;
        }

        // Get branch for site
        $this->info('Getting branch for site...');
        $sites = $this->getConsoleSites($this->getForgeHttpRequest(), true);
        $chosenSite = $sites->firstWhere('name', $site);
        Log::debug('Chosen site:', [$chosenSite]);
        if (!$chosenSite) {
            $this->error('Site not found.');
            return 1;
        }
        $siteBranch = $chosenSite->repository_branch;

        // Get issues from branch
        $this->info('Getting issues from branch...');
        $branchIssues = Str::of($siteBranch)->after('release/')->after('/')->explode('-');
        Log::debug('Branch issues:', $branchIssues->toArray());
        $branchVersion = Str::of($siteBranch)->after('release/')->before('/');
        Log::debug('Branch version:', [$branchVersion]);
        $incrementedVersion = $this->incrementPrereleaseNumber($branchVersion);

        // Sync branch issues with given issues
        $this->info('Syncing branch issues with given issues...');
        $issues = collect($issues);
        $issues = $isAdding
            ? $branchIssues->merge($issues)->unique()
            : $branchIssues->diff($issues);

        // If we're only adding issues, let's just use the current branch
        if ($isAdding) {
            $this->info('Adding issues to current branch...');
            $issuesFormattedForBranch = $issues->implode('-');
            $branchName = "release/$incrementedVersion/$issuesFormattedForBranch";
            $this->runProcess("git checkout {$siteBranch}");
            $this->runProcess("git pull origin {$siteBranch}");
            $this->runProcess("git checkout -b {$branchName}");

            // Merge the new issues
            $addedIssues = $issues->diff($branchIssues);
            $branches = $this->findIssueBranches($addedIssues->toArray());
            $this->mergeBranches($branches);

            $this->runProcess("npm version prerelease");
            $this->runProcess("git push origin {$branchName}");

            $this->info("New branch: {$branchName}");
            return 0;
        }


        // Create new release branch
        $this->info('Creating new release branch...');
        $this->call('create-release-branch', [
            'level' => $incrementedVersion,
            'issues' => $issues->implode(','),
        ]);

        $newBranch = $this->runProcess('git branch --show-current');

        // Update the site branch and deploy
        Log::debug('New branch:' . $newBranch);
        $this->info('Updating the site branch and deploying...');
        $this->call('update-site-branch', [
            'site' => $site,
            'branch' => $newBranch,
        ]);
    }

    public function incrementPrereleaseNumber($version)
    {
        $prereleaseNumber = +preg_replace('/[^0-9]/', '', Str::of($version)->afterLast('-'));
        $newPrereleaseNumber = $prereleaseNumber + 1;
        return Str::of($version)->beforeLast('-')->append("-{$newPrereleaseNumber}");
    }
}
