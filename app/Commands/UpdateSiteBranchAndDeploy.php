<?php

namespace App\Commands;

use App\Services\ChoosesBranch;
use App\Services\GetsConsoleSites;
use App\Services\UsesForgeHttp;
use Illuminate\Support\Facades\Log;
use LaravelZero\Framework\Commands\Command;

class UpdateSiteBranchAndDeploy extends Command {
    use GetsConsoleSites;
    use UsesForgeHttp;
    use ChoosesBranch;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update-site-branch-and-deploy {site?} {branch?}';

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

        $this->client = $this->getForgeHttpRequest();

        // Get the site from forge API by getting servers, then sites, then filtering by site name
        $sites = $this->getConsoleSites($this->client);
        
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

        // If the site is blacklabsconsole.com, warn and make the user confirm by typing the site name
        if ($chosenSite->name == 'blacklabsconsole.com') {
            $this->warn('[WARNING] You are about to deploy to production!');
            $inputName = $this->ask("Please type the name of the site to continue.");
            if ($inputName !== $chosenSite->name) {
                $this->error("You didn't type the name of the site correctly. Aborting.");
                return 1;
            }
        }

        $this->info("Updating site {$chosenSite->id}...");

        $chosenSite->repository_branch = $this->chooseBranch(
            overrideBranch: $branch,
            message: "Which branch would you like to deploy?"
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
