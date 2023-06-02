<?php

namespace App\Commands;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use LaravelZero\Framework\Commands\Command;

class DevopsShowSiteBranches extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'devops:show-site-branches';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Shows all console sites and the branches they are on.';

    public $client = null;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $token = Storage::get('forge-api-token.txt');
        if (!$token) {
            $this->error('No API token found.');
            $this->warn('Please run `app:store-forge-api-token` with your Forge API Token first.');
            return;
        }

        $this->client = Http::withToken($token)->acceptJson()->contentType('application/json');
        
        // Get the sites from forge API by getting servers, then sites
        $servers = $this->client->get('https://forge.laravel.com/api/v1/servers')->getBody()->getContents();

        $servers = collect(json_decode($servers)->servers)
            ->filter(fn ($server) => collect($server->tags)->map(fn ($tag) => $tag->name)->contains('console'));

        $sites = collect($servers)->flatMap(function ($server) {
            $result = $this->client->get('https://forge.laravel.com/api/v1/servers/' . $server->id . '/sites')
                ->getBody()->getContents();
            return collect(json_decode($result)->sites);
        });

        $sites = $sites->map(function ($site) {
            return [
                'name' => $site->name,
                'branch' => $site->repository_branch,
            ];
        });

        $this->table(['Site', 'Branch'], $sites);
    }
}
