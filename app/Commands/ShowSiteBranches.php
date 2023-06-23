<?php

namespace App\Commands;

use App\Services\GetsConsoleSites;
use App\Services\UsesForgeHttp;
use LaravelZero\Framework\Commands\Command;

class ShowSiteBranches extends Command
{
    use UsesForgeHttp;
    use GetsConsoleSites;

    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'show-site-branches';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Shows all console sites and the branches they are on.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Get the sites from forge API by getting servers, then sites
        $sites = $this->getConsoleSites($this->getForgeHttpRequest());

        $this->table(['Site', 'Branch'], $sites->map(function ($site) {
            return [
                'name' => $site->name,
                'branch' => $site->repository_branch,
            ];
        }));
    }
}
