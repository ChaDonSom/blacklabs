<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

trait GetsConsoleSites
{
    public function getConsoleSites(\Illuminate\Http\Client\PendingRequest $request, $force = false)
    {
        $fileName = app()->runningUnitTests() ? 'forge-sites-test.json' : 'forge-sites.json';
        $siteNamesFileName = app()->runningUnitTests() ? 'forge-site-names-test.json' : 'forge-site-names.json';
        // If $fileName is new enough, use it
        if (
            Storage::exists($fileName)
            && Storage::lastModified($fileName) > (now()->subSeconds(20)->valueOf() / 1000)
            && !$force
        ) {
            $this->info('Got sites from 20-second storage');
            return collect(json_decode(Storage::get($fileName)));
        }

        if (
            Storage::exists($siteNamesFileName)
            && Storage::lastModified($siteNamesFileName) > (now()->subDays(1)->valueOf() / 1000)
            && !$force
        ) {
            $siteNames = collect(json_decode(Storage::get($siteNamesFileName)));
            $siteNames = $siteNames->mapWithKeys(fn($value, $key) => [$key => (object) ['name' => $value, 'id' => $key]]);
            $this->info('Got site names from daily storage');
            return $siteNames;
        }

        $servers = $request->get('https://forge.laravel.com/api/v1/servers')->getBody()->getContents();

        $servers = collect(json_decode($servers)->servers)
            ->filter(fn($server) => collect($server->tags)->map(fn($tag) => $tag->name)->contains('console'));

        Storage::put($fileName, json_encode(collect($servers)->flatMap(function ($server) use ($request) {
            $result = $request->get('https://forge.laravel.com/api/v1/servers/' . $server->id . '/sites')
                ->getBody()->getContents();
            return collect(json_decode($result)->sites)
                ->map(function ($site) use ($server) {
                    $site->server_id = $server->id;
                    return $site;
                });
        })));

        $gotFromStorage = collect(json_decode(Storage::get($fileName)));

        Storage::put($siteNamesFileName, json_encode($servers->flatMap(function ($server) use ($gotFromStorage) {
            $result = $gotFromStorage->where('server_id', $server->id);
            return collect($result)->mapWithKeys(fn($site) => [$site->id => $site->name]);
        })));

        return $gotFromStorage;
    }

    public function getSiteNames(\Illuminate\Http\Client\PendingRequest $request, $force = false)
    {
        $fileName = app()->runningUnitTests() ? 'forge-site-names-test.json' : 'forge-site-names.json';
        // If $fileName is new enough, use it
        if (
            Storage::exists($fileName)
            && Storage::lastModified($fileName) > (now()->subDays(1)->valueOf() / 1000)
            && !$force
        ) {
            $siteNames = collect(json_decode(Storage::get($fileName)));
            $siteNames = $siteNames->mapWithKeys(fn($value, $key) => [$key => (object) ['name' => $value, 'id' => $key]]);
            $this->info('Got site names from daily storage');
            return $siteNames;
        }

        $servers = $request->get('https://forge.laravel.com/api/v1/servers')->getBody()->getContents();

        $servers = collect(json_decode($servers)->servers)
            ->filter(fn($server) => collect($server->tags)->map(fn($tag) => $tag->name)->contains('console'));

        $gotFromStorage = collect(json_decode(Storage::get('forge-sites.json')));

        Storage::put($fileName, json_encode($servers->flatMap(function ($server) use ($gotFromStorage) {
            $result = $gotFromStorage->where('server_id', $server->id);
            return collect($result)->mapWithKeys(fn($site) => [$site->id => $site->name]);
        })));

        return collect(json_decode(Storage::get($fileName)));
    }
}
