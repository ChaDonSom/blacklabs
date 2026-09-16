<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Storage;

trait GetsConsoleSites
{
    private const CONSOLE_TAG_ID = 7445;
    public function getConsoleSites(PendingRequest $request, $force = false)
    {
        $fileName = app()->runningUnitTests() ? 'forge-sites-test.json' : 'forge-sites.json';
        $siteNamesFileName = app()->runningUnitTests() ? 'forge-site-names-test.json' : 'forge-site-names.json';
        // If $fileName is new enough, use it
        if (
            Storage::exists($fileName)
            && Storage::lastModified($fileName) > (now()->subSeconds(20)->valueOf() / 1000)
            && ! $force
        ) {
            $this->info('Got sites from 20-second storage');

            return collect(json_decode(Storage::get($fileName)));
        }

        if (
            Storage::exists($siteNamesFileName)
            && Storage::lastModified($siteNamesFileName) > (now()->subDays(1)->valueOf() / 1000)
            && ! $force
        ) {
            $siteNames = collect(json_decode(Storage::get($siteNamesFileName)));
            $siteNames = $siteNames->mapWithKeys(fn($value, $key) => [$key => (object) ['name' => $value, 'id' => $key]]);
            $this->info('Got site names from daily storage');

            return $siteNames;
        }

        $servers = $request->get('https://forge.laravel.com/api/orgs/' . $this->getForgeOrgId() . '/servers?include=tags')->getBody()->getContents();

        $servers = collect(json_decode($servers)->data)
            ->filter(fn($server) => collect($server->relationships->tags->data)->map(fn($tag) => $tag->id)->contains(self::CONSOLE_TAG_ID));

        Storage::put($fileName, json_encode(collect($servers)->flatMap(function ($server) use ($request) {
            $result = $request->get('https://forge.laravel.com/api/orgs/' . $this->getForgeOrgId() . '/servers/' . $server->id . '/sites')
                ->getBody()->getContents();

            return collect(json_decode($result)->data)
                ->map(function ($site) use ($server) {
                    $site->attributes->server_id = $server->id;

                    return $site;
                });
        })));

        $gotFromStorage = collect(json_decode(Storage::get($fileName)));

        Storage::put($siteNamesFileName, json_encode($servers->flatMap(function ($server) use ($gotFromStorage) {
            $result = $gotFromStorage->where('server_id', $server->id);

            return collect($result)->mapWithKeys(fn($site) => [$site->id => $site->attributes->name]);
        })));

        return $gotFromStorage;
    }

    public function getSiteNames(PendingRequest $request, $force = false)
    {
        $fileName = app()->runningUnitTests() ? 'forge-site-names-test.json' : 'forge-site-names.json';
        // If $fileName is new enough, use it
        if (
            Storage::exists($fileName)
            && Storage::lastModified($fileName) > (now()->subDays(1)->valueOf() / 1000)
            && ! $force
        ) {
            $siteNames = collect(json_decode(Storage::get($fileName)));
            $siteNames = $siteNames->mapWithKeys(fn($value, $key) => [$key => (object) ['name' => $value, 'id' => $key]]);
            $this->info('Got site names from daily storage');

            return $siteNames;
        }

        $servers = $request->get('https://forge.laravel.com/api/orgs/' . $this->getForgeOrgId() . '/servers')->getBody()->getContents();

        $servers = collect(json_decode($servers)->data)
            ->filter(fn($server) => collect($server->relationships->tags->data)->map(fn($tag) => $tag->id)->contains(self::CONSOLE_TAG_ID));

        $gotFromStorage = collect(json_decode(Storage::get($fileName)));

        Storage::put($fileName, json_encode($servers->flatMap(function ($server) use ($gotFromStorage) {
            $result = $gotFromStorage->where('server_id', $server->id);

            return collect($result)->mapWithKeys(fn($site) => [$site->id => $site->attributes->name]);
        })));

        return collect(json_decode(Storage::get($fileName)));
    }
}
