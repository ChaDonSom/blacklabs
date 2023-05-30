<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use LaravelZero\Framework\Commands\Command;

class UpdateSiteBranchAndDeploy extends Command {
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'devops:update-site-branch-and-deploy {site?} {branch?}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update the chosen site\'s branch and deploy it';

    public $client = null;

    /**
     * Execute the console command.
     */
    public function handle() {
        $site = $this->argument('site');
        $branch = $this->argument('branch');

        $token = Storage::get('forge-api-token.txt');
        if (!$token) {
            $this->error('No API token found.');
            $this->warn('Please run `app:store-forge-api-token` with your Forge API Token first.');
            return;
        }

        $this->client = Http::withToken($token)->acceptJson()->contentType('application/json');

        // Get the site from forge API by getting servers, then sites, then filtering by site name
        $servers = $this->client->get('https://forge.laravel.com/api/v1/servers')->getBody()->getContents();
        
        $servers = collect(json_decode($servers)->servers)
            ->filter(fn ($server) => collect($server->tags)->map(fn ($tag) => $tag->name)->contains('console'));
        
        $sites = collect($servers)->flatMap(function ($server) {
            $result = $this->client->get('https://forge.laravel.com/api/v1/servers/' . $server->id . '/sites')
                ->getBody()->getContents();
            return collect(json_decode($result)->sites);
        });
        
        // Ask for the site and provide options from forge's API
        $chosenSite = $site ?? $this->anticipate(
            'Which site would you like to update and deploy?',
            function (string $input) use ($sites) {
                return $sites
                    ->map(fn ($s) => $s->name)
                    ->filter(fn ($name) => str_starts_with($name, $input))
                    ->toArray();
            }
        );

        $chosenSite = $sites->firstWhere('name', $chosenSite);
        $this->info("Updating site {$chosenSite->id}...");

        // Get the branches using git in the current folder
        $branches = collect(explode("\n", shell_exec('git branch -r')))
            ->map(fn ($branch) => trim(str_replace('origin/', '', $branch)))
            ->filter(fn ($branch) => $branch != 'HEAD' && $branch != 'master')
            ->values();

        Log::debug($branches);

        $chosenSite->repository_branch = $branch ?? $this->anticipate(
            'What branch would you like to deploy?',
            function (string $input) use ($branches) {
                return $branches
                    ->filter(fn ($branch) => strpos($branch, $input) !== false)
                    ->toArray();
            }
        );

        // Update the site using forge's API
        $this->info("Updating site {$chosenSite->name} to branch {$chosenSite->repository_branch}...");
        $this->client->put(
            'https://forge.laravel.com/api/v1/servers/' . $chosenSite->server_id . '/sites/' . $chosenSite->id . '/git',
            [
                'branch' => $chosenSite->repository_branch
            ]
        );

        // Deploy it!
        $this->info("Deploying site {$chosenSite->name}...");
        $this->client->post(
            'https://forge.laravel.com/api/v1/servers/' . $chosenSite->server_id . '/sites/' . $chosenSite->id . '/deployment/deploy',
        );

        $this->info("Site {$chosenSite->name} updated and deployment triggered successfully.");
    }
}
