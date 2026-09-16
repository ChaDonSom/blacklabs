<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

it('errors if you don\'t specify add-issues or remove-issues', function () {
    $this->artisan('site add-some example.com 123')
        ->assertExitCode(1);

    $this->artisan('site remove-some example.com 123')
        ->assertExitCode(1);
});

it('adds 1 issue', function () {
    fakeForgeApi('release/v1.0.0/456');
    $x = $this->artisan('create-release-branch v1.0.0 456');
    Log::debug('branches', [$this->runProcess('git branch')]);

    $this->artisan('site add-issues example.com 123')
        ->expectsOutput('Getting branch for site...')
        // ->expectsOutput('Getting tag from branch...')
        // ->expectsOutput('Getting latest git tag that matches and incrementing it...')
        ->expectsOutput('Getting issues from branch...')
        ->expectsOutput('Syncing branch issues with given issues...')
        // ->expectsOutput('Updating the site branch and deploying...')
        ->assertExitCode(0);

    removeForgeOrgId();
})->group('dummy-git-repo')->todo();

it('adds 2 issues', function () {
    fakeForgeApi('release/v1.0.0/123');
    $this->artisan('create-release-branch v1.0.0 123');

    // Add another issue so we can add 2
    $this->branchThreeName = '324-' . Str::kebab(collect(fake()->words())->join('-'));
    $this->repo->createBranch($this->branchThreeName, true);
    touch('./README-324.md');
    $this->repo->addAllChanges();
    $this->repo->commit('324 commit');
    exec('git push --set-upstream origin ' . $this->branchThreeName);

    $this->artisan('site add-issues example.com 456,324')
        ->expectsOutput('Getting branch for site...')
        // ->expectsOutput('Getting tag from branch...')
        // ->expectsOutput('Getting latest git tag that matches and incrementing it...')
        ->expectsOutput('Getting issues from branch...')
        ->expectsOutput('Syncing branch issues with given issues...')
        ->expectsOutput('Updating the site branch and deploying...')
        ->assertExitCode(0);

    removeForgeOrgId();
})->group('dummy-git-repo')->todo();

it('removes 1 issue', function () {
    fakeForgeApi('release/v1.0.0/123-456');
    $this->artisan('create-release-branch 1.0.0 123,456');

    $this->artisan('site remove-issues example.com 123')
        ->expectsOutput('Getting branch for site...')
        // ->expectsOutput('Getting tag from branch...')
        // ->expectsOutput('Getting latest git tag that matches and incrementing it...')
        ->expectsOutput('Getting issues from branch...')
        ->expectsOutput('Syncing branch issues with given issues...')
        ->expectsOutput('Creating new release branch...')
        ->expectsOutput('Updating the site branch and deploying...')
        ->assertExitCode(0);

    removeForgeOrgId();
})->group('dummy-git-repo')->todo();

it('removes 2 issues', function () {
    fakeForgeApi('release/v1.0.0/123-456-324');

    // Add another issue so we can remove 2
    $this->branchThreeName = '324-' . Str::kebab(collect(fake()->words())->join('-'));
    $this->repo->createBranch($this->branchThreeName, true);
    touch('./README-324.md');
    $this->repo->addAllChanges();
    $this->repo->commit('324 commit');
    exec('git push --set-upstream origin ' . $this->branchThreeName);

    $this->artisan('create-release-branch 1.0.0 123,456,324');

    $this->artisan('site remove-issues example.com 123,456')
        ->expectsOutput('Getting branch for site...')
        // ->expectsOutput('Getting tag from branch...')
        // ->expectsOutput('Getting latest git tag that matches and incrementing it...')
        ->expectsOutput('Getting issues from branch...')
        ->expectsOutput('Syncing branch issues with given issues...')
        ->expectsOutput('Creating new release branch...')
        ->expectsOutput('Updating the site branch and deploying...')
        ->assertExitCode(0);

    removeForgeOrgId();
})->group('dummy-git-repo')->todo();

/**
 * I tried using Pest group, but Http didn't carry over that way. The Http calls were going through to the real Forge API.
 */
function fakeForgeApi(string $releaseBranchName, string $siteName = 'example.com')
{
    $orgId = '1';
    $fakeServers = [
        'data' => [
            [
                'id' => 123,
                'attributes' => [
                    'name' => 'example',
                    'ip_address' => '12.34.56.78',
                ],
                'relationships' => [
                    'tags' => [
                        'data' => [
                            [
                                'id' => 7445,
                                'name' => 'console',
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ];
    $fakeSites = [
        'data' => [
            [
                'id' => 123,
                'attributes' => [
                    'name' => $siteName,
                    'repository' => [
                        'branch' => $releaseBranchName,
                    ],
                ],
            ],
        ],
    ];
    Http::preventStrayRequests();
    Http::fake([
        'forge.laravel.com/api/orgs/*/servers?*' => Http::sequence()->push($fakeServers)->whenEmpty(Http::response($fakeServers)),
        'forge.laravel.com/api/orgs/*/servers' => Http::sequence()->push($fakeServers)->whenEmpty(Http::response($fakeServers)),
        'forge.laravel.com/api/orgs/*/servers/123/sites' => Http::sequence()->push($fakeSites)->whenEmpty(Http::response($fakeSites)),
        'forge.laravel.com/api/orgs/*/servers/123/sites/123*' => Http::sequence()->whenEmpty(Http::response(null, 200)),
    ]);
    // Store org ID for tests
    \Illuminate\Support\Facades\Storage::put('forge-org-id.txt', $orgId);
}

function removeForgeOrgId()
{
    \Illuminate\Support\Facades\Storage::delete('forge-org-id.txt');
}
