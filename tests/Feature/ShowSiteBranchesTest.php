<?php

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('doesn\'t throw an error', function () {
    fakeShowSiteBranchesForgeApi('release/v1.0.0/123');
    $this->artisan('show-site-branches')->assertExitCode(0);
});

it('reports Forge errors instead of accessing a missing data property', function () {
    Storage::put('forge-org-id.txt', '1');
    Http::preventStrayRequests();
    Http::fake([
        'forge.laravel.com/api/orgs/*/servers?*' => Http::response([
            'message' => 'Unauthenticated.',
        ], 401),
    ]);

    expect(fn() => $this->artisan('show-site-branches'))
        ->toThrow(RequestException::class, '401');
});

function fakeShowSiteBranchesForgeApi(string $releaseBranchName): void
{
    Storage::put('forge-org-id.txt', '1');
    Http::preventStrayRequests();
    Http::fake([
        'forge.laravel.com/api/orgs/*/servers?*' => Http::response([
            'data' => [[
                'id' => 123,
                'relationships' => [
                    'tags' => ['data' => [['id' => 7445]]],
                ],
            ]],
        ]),
        'forge.laravel.com/api/orgs/*/servers/123/sites' => Http::response([
            'data' => [[
                'id' => 123,
                'attributes' => [
                    'name' => 'example.com',
                    'repository' => ['branch' => $releaseBranchName],
                ],
            ]],
        ]),
    ]);
}
