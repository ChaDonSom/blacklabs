<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('waits for Forge to install the selected branch before deploying', function () {
    Storage::put('forge-org-id.txt', '1');

    $servers = [
        'data' => [[
            'id' => 456,
            'relationships' => ['tags' => ['data' => [['id' => 7445]]]],
        ]],
    ];
    $sites = [
        'data' => [[
            'id' => 123,
            'attributes' => [
                'name' => 'example.com',
                'repository' => [
                    'provider' => 'github',
                    'branch' => 'main',
                    'status' => 'installed',
                ],
            ],
        ]],
    ];
    $siteResponse = fn(string $branch, string $status) => [
        'data' => [[
            'id' => '123',
            'type' => 'sites',
            'attributes' => [
                'repository' => [
                    'branch' => $branch,
                    'status' => $status,
                ],
            ],
        ]],
    ];

    Http::preventStrayRequests();
    Http::fake([
        'forge.laravel.com/api/orgs/*/servers?*' => Http::response($servers),
        'forge.laravel.com/api/orgs/*/servers/456/sites' => Http::response($sites),
        'forge.laravel.com/api/orgs/*/servers/456/sites/123/git' => Http::response([], 202),
        'forge.laravel.com/api/orgs/*/servers/456/sites?*' => Http::sequence()
            ->push($siteResponse('main', 'installed'))
            ->push($siteResponse('release/v1.0.0/123', 'installing'))
            ->push($siteResponse('release/v1.0.0/123', 'installed')),
        'forge.laravel.com/api/orgs/*/servers/456/sites/123/deployments' => Http::response([], 202),
    ]);

    $this->artisan('update-site-branch-and-deploy example.com release/v1.0.0/123')
        ->expectsOutput('Waiting for Forge to finish updating the site branch...')
        ->expectsOutput('Site example.com updated and deployment triggered successfully.')
        ->assertExitCode(0);

    $requestOrder = Http::recorded()
        ->map(fn($recorded) => $recorded[0]->method() . ' ' . parse_url($recorded[0]->url(), PHP_URL_PATH))
        ->all();

    expect($requestOrder)->toBe([
        'GET /api/orgs/1/servers',
        'GET /api/orgs/1/servers/456/sites',
        'PUT /api/orgs/1/servers/456/sites/123/git',
        'GET /api/orgs/1/servers/456/sites',
        'GET /api/orgs/1/servers/456/sites',
        'GET /api/orgs/1/servers/456/sites',
        'POST /api/orgs/1/servers/456/sites/123/deployments',
    ]);
});

it('warns if you pick production', function () {
    fakeForgeApi('release/v1.0.0/123', 'blacklabsconsole.com');
    $this->artisan('update-site-branch-and-deploy blacklabsconsole.com forge-production')
        ->expectsOutput('[WARNING] You are about to deploy to production! This will change the branch of production away from forge-production!')
        ->expectsQuestion('Please type the name of the site to continue.', '')
        ->expectsOutput('You didn\'t type the name of the site correctly. Aborting.')
        ->assertExitCode(1);
    fakeForgeApi('release/v1.0.0/123', 'blacklabsconsole.com');
    $this->artisan('update-site-branch-and-deploy blacklabsconsole.com')
        // ->expectsQuestion('Which site would you like to update and deploy?', 'blacklabsconsole.com')
        ->expectsOutput('[WARNING] You are about to deploy to production! This will change the branch of production away from forge-production!')
        ->expectsQuestion('Please type the name of the site to continue.', '')
        ->expectsOutput('You didn\'t type the name of the site correctly. Aborting.')
        ->assertExitCode(1);
});
