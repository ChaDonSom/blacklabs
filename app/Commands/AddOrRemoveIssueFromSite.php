<?php

namespace App\Commands;

use App\Services\GetsConsoleSites;
use App\Services\RunsProcesses;
use App\Services\Tags;
use App\Services\UsesForgeHttp;
use Illuminate\Support\Str;
use LaravelZero\Framework\Commands\Command;

class AddOrRemoveIssueFromSite extends Command
{
    use GetsConsoleSites;
    use UsesForgeHttp;
    use Tags;
    use RunsProcesses;

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
        $issues = $this->argument('issues');
        $issues = explode(',', $issues);

        $isAdding = $this->argument('add-issues') === 'add-issues';
        if (!$isAdding && $this->argument('add-issues') !== 'remove-issues') {
            $this->error('You must specify whether you are adding or removing issues.');
            return 1;
        }

        // Get branch for site
        $this->info('Getting branch for site...');
        $sites = $this->getConsoleSites($this->getForgeHttpRequest());
        $chosenSite = $sites->firstWhere('name', $site);
        if (!$chosenSite) {
            $this->error('Site not found.');
            return 1;
        }
        $siteBranch = $chosenSite->repository_branch;

        // Get tag from branch
        $this->info('Getting tag from branch...');
        $latestTag = $this->getTagFromBranch($siteBranch);

        // Get latest git tag that matches and increment it
        $this->info('Getting latest git tag that matches and incrementing it...');
        $gitTag = $this->getGitTagFromBranchTag($latestTag);
        $nextTag = $this->incrementTag($gitTag);

        // Get issues from branch
        $this->info('Getting issues from branch...');
        $branchIssues = Str::of($siteBranch)->after('release/')->explode('-')->slice(1);

        // Sync branch issues with given issues
        $this->info('Syncing branch issues with given issues...');
        $issues = collect($issues);
        $issues = $isAdding
            ? $branchIssues->merge($issues)->unique()->sort()
            : $branchIssues->diff($issues)->sort();

        // Create new release branch
        $this->info('Creating new release branch...');
        $this->call('create-release-branch', [
            'version' => $nextTag,
            'issues' => $issues->implode(','),
        ]);

        // Update the site branch and deploy
        $this->info('Updating the site branch and deploying...');
        $this->call('update-site-branch', [
            'site' => $site,
            'branch' => "release/$nextTag-" . $issues->implode('-'),
        ]);
    }
}
