<?php

namespace App\Services;

trait GetsConsoleSites {
    public function getConsoleSites(\Illuminate\Http\Client\PendingRequest $request) {
        // Get the sites from forge API by getting servers, then sites
        $servers = $request->get('https://forge.laravel.com/api/v1/servers')->getBody()->getContents();

        $servers = collect(json_decode($servers)->servers)
            ->filter(fn ($server) => collect($server->tags)->map(fn ($tag) => $tag->name)->contains('console'));

        return collect($servers)->flatMap(function ($server) use ($request) {
            $result = $request->get('https://forge.laravel.com/api/v1/servers/' . $server->id . '/sites')
                ->getBody()->getContents();
            return collect(json_decode($result)->sites);
        });
    }
}