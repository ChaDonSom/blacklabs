<?php

namespace App\Commands;

use App\Services\ChoosesBranch;
use App\Services\GetsConsoleSites;
use App\Services\UsesForgeHttp;
use Illuminate\Support\Facades\Log;
use LaravelZero\Framework\Commands\Command;

use function Laravel\Prompts\search;

class UpdateSiteBranchAndDeploy extends Command
{
    private const BRANCH_UPDATE_TIMEOUT_SECONDS = 120;

    private const BRANCH_UPDATE_POLL_INTERVAL_MICROSECONDS = 2_000_000;

    use ChoosesBranch;
    use GetsConsoleSites;
    use UsesForgeHttp;

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
    public function handle()
    {
        $site = $this->argument('site');
        $branch = $this->argument('branch');

        $this->client = $this->getForgeHttpRequest();

        // Get the site from forge API by getting servers, then sites, then filtering by site name
        $sites = $this->getConsoleSites($this->client, true);
        Log::debug('Sites:', [$sites]);

        // Ask for the site and provide options from forge's API
        $chosenSite = $site ?? search(
            label: 'Which site would you like to update and deploy?',
            options: function (string $input) use ($sites) {
                return $sites
                    ->map(fn($s) => $s->attributes->name)
                    ->filter(fn($name) => str_contains(strtolower($name), strtolower($input)))
                    ->keyBy(fn($name) => $name)
                    ->toArray();
            },
            scroll: 10,
        );

        $chosenSite = $sites->firstWhere('attributes.name', $chosenSite);

        // If the site is blacklabsconsole.com, warn and make the user confirm by typing the site name
        if ($chosenSite->attributes->name == 'blacklabsconsole.com') {
            $this->warn('[WARNING] You are about to deploy to production! This will change the branch of production away from forge-production!');
            $inputName = $this->ask('Please type the name of the site to continue.');
            if ($inputName !== $chosenSite->attributes->name) {
                $this->error("You didn't type the name of the site correctly. Aborting.");

                return 1;
            }
        }

        $this->info("Updating site {$chosenSite->attributes->name}...");

        $chosenBranch = $this->chooseBranch(
            overrideBranch: $branch,
            message: 'Which branch would you like to deploy?'
        );

        // Update the site using forge's API
        $this->info("Updating site {$chosenSite->attributes->name} to branch {$chosenBranch}...");
        $putResult = $this->client->put(
            'https://forge.laravel.com/api/orgs/' . $this->getForgeOrgId() . '/servers/' . $chosenSite->attributes->server_id . '/sites/' . $chosenSite->id . '/git',
            [
                'source_control_provider' => strtolower($chosenSite->attributes->repository->provider),
                'repository' => 'blacklabapps/console',
                'branch' => $chosenBranch,
            ]
        );

        if ($putResult->failed()) {
            dd($putResult->json());
        }

        $this->info('Waiting for Forge to finish updating the site branch...');
        if (! $this->waitForBranchUpdate(
            $chosenSite->attributes->server_id,
            $chosenSite->id,
            $chosenSite->attributes->name,
            $chosenBranch,
        )) {
            return 1;
        }

        // Deploy it!
        $this->info("Deploying site {$chosenSite->attributes->name}...");
        $postResult = $this->client->post(
            'https://forge.laravel.com/api/orgs/' . $this->getForgeOrgId() . '/servers/' . $chosenSite->attributes->server_id . '/sites/' . $chosenSite->id . '/deployments',
        );

        if ($postResult->failed()) {
            dd($postResult->json());
        }

        $this->info("Site {$chosenSite->attributes->name} updated and deployment triggered successfully.");
    }

    private function waitForBranchUpdate($serverId, $siteId, string $siteName, string $branch): bool
    {
        $url = 'https://forge.laravel.com/api/orgs/' . $this->getForgeOrgId() . "/servers/{$serverId}/sites";
        $deadline = hrtime(true) + self::BRANCH_UPDATE_TIMEOUT_SECONDS * 1_000_000_000;

        while (hrtime(true) < $deadline) {
            $response = $this->client->get($url, ['filter[name]' => $siteName]);
            $response->throw();

            $site = collect($response->json('data', []))->first(
                fn ($site) => (string) ($site['id'] ?? '') === (string) $siteId,
            );
            $repository = $site['attributes']['repository'] ?? [];
            if (($repository['branch'] ?? null) === $branch && ($repository['status'] ?? null) === 'installed') {
                return true;
            }

            $remainingNanoseconds = $deadline - hrtime(true);
            if ($remainingNanoseconds > 0) {
                usleep((int) min(self::BRANCH_UPDATE_POLL_INTERVAL_MICROSECONDS, $remainingNanoseconds / 1_000));
            }
        }

        $this->error("Forge did not confirm branch '{$branch}' within " . self::BRANCH_UPDATE_TIMEOUT_SECONDS . ' seconds. Deployment was not triggered.');

        return false;
    }
}
