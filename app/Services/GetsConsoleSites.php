<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

trait GetsConsoleSites
{
    public function getConsoleSites(\Illuminate\Http\Client\PendingRequest $request)
    {
        // If forge-sites.json is new enough, use it
        if (
            Storage::exists('forge-sites.json')
            && Storage::lastModified('forge-sites.json') > (now()->subMinutes(5)->valueOf() / 1000)
        ) {
            return collect(json_decode(Storage::get('forge-sites.json')));
        }

        $servers = $request->get('https://forge.laravel.com/api/v1/servers')->getBody()->getContents();

        $servers = collect(json_decode($servers)->servers)
            ->filter(fn ($server) => collect($server->tags)->map(fn ($tag) => $tag->name)->contains('console'));

        Storage::put('forge-sites.json', json_encode(collect($servers)->flatMap(function ($server) use ($request) {
            $result = $request->get('https://forge.laravel.com/api/v1/servers/' . $server->id . '/sites')
                ->getBody()->getContents();
            return collect(json_decode($result)->sites);
        })));

        return collect(json_decode(Storage::get('forge-sites.json')));
    }
}
