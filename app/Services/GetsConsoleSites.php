<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

trait GetsConsoleSites
{
    public function getConsoleSites(\Illuminate\Http\Client\PendingRequest $request)
    {
        $fileName = app()->runningUnitTests() ? 'forge-sites-test.json' : 'forge-sites.json';
        // If $fileName is new enough, use it
        if (
            Storage::exists($fileName)
            && Storage::lastModified($fileName) > (now()->subMinutes(5)->valueOf() / 1000)
        ) {
            return collect(json_decode(Storage::get($fileName)));
        }

        $servers = $request->get('https://forge.laravel.com/api/v1/servers')->getBody()->getContents();

        $servers = collect(json_decode($servers)->servers)
            ->filter(fn ($server) => collect($server->tags)->map(fn ($tag) => $tag->name)->contains('console'));

        Storage::put($fileName, json_encode(collect($servers)->flatMap(function ($server) use ($request) {
            $result = $request->get('https://forge.laravel.com/api/v1/servers/' . $server->id . '/sites')
                ->getBody()->getContents();
            return collect(json_decode($result)->sites);
        })));

        return collect(json_decode(Storage::get($fileName)));
    }
}
